<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\DropAggregator;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\DefaultAttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\FilteredAttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use Nevay\OTelSDK\Metrics\Internal\Instrument\ObservableCallbackDestructor;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\Stream\AsynchronousMetricStream;
use Nevay\OTelSDK\Metrics\Internal\Stream\DefaultMetricAggregator;
use Nevay\OTelSDK\Metrics\Internal\Stream\DefaultMetricAggregatorFactory;
use Nevay\OTelSDK\Metrics\Internal\Stream\SynchronousMetricStream;
use Nevay\OTelSDK\Metrics\Internal\View\ResolvedView;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OTelSDK\Metrics\MeterConfig;
use Nevay\OTelSDK\Metrics\MetricReader;
use Psr\Log\LoggerInterface;
use WeakMap;
use function array_search;
use function bin2hex;
use function hash;
use function preg_match;
use function serialize;
use function spl_object_id;
use function strtolower;

/**
 * @internal
 */
final class MeterState {

    private Resource $resource;
    /** @var array<MetricReader> */
    public array $metricReaders = [];
    /** @var array<MeterMetricProducer> */
    private array $metricProducers = [];
    public ExemplarFilter $exemplarFilter;
    /** @var Closure(Aggregator): ExemplarReservoir */
    public Closure $exemplarReservoir;
    public ViewRegistry $viewRegistry;

    private ?int $startTimestamp = null;

    /** @var array<int, array<string, RegisteredInstrument>> */
    private array $instruments = [];
    /** @var array<int, array<string, int>> */
    private array $instrumentIdentities = [];

    /**
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     */
    public function __construct(
        public readonly MetricRegistry $registry,
        private readonly Clock $clock,
        private readonly StalenessHandlerFactory $stalenessHandlerFactory,
        public readonly WeakMap $destructors,
        public readonly ?LoggerInterface $logger,
    ) {}

    public function updateResource(Resource $resource): void {
        $this->resource = $resource;
        foreach ($this->metricReaders as $metricReader) {
            $metricReader->updateResource($resource);
        }
    }

    public function updateConfig(MeterConfig $meterConfig, InstrumentationScope $instrumentationScope): void {
        $startTimestamp = $this->clock->now();
        foreach ($this->instruments[spl_object_id($instrumentationScope)] ?? [] as $r) {
            if ($r->dormant && !$meterConfig->disabled) {
                $this->createStreams($r->instrument, $r->instrumentationScope, $startTimestamp);
                $r->dormant = false;
            }
            if (!$r->dormant && $meterConfig->disabled) {
                $this->releaseStreams($r->instrument);
                $r->dormant = true;
            }
        }
    }

    public function reload(): void {
        $startTimestamp = $this->clock->now();
        foreach ($this->instruments as $instruments) {
            foreach ($instruments as $r) {
                if (!$r->dormant) {
                    $this->createStreams($r->instrument, $r->instrumentationScope, $startTimestamp);
                }
            }
        }
    }

    public function register(MetricReader $metricReader): void {
        $this->metricReaders[] = $metricReader;
        $this->metricProducers[] = $metricProducer = new MeterMetricProducer($this->registry);
        $metricReader->updateResource($this->resource);
        $metricReader->registerProducer($metricProducer);

        $startTimestamp = $this->clock->now();
        foreach ($this->instruments as $instruments) {
            foreach ($instruments as $r) {
                if (!$r->dormant) {
                    $this->createStreams($r->instrument, $r->instrumentationScope, $startTimestamp);
                }
            }
        }
    }

    public function unregister(MetricReader $metricReader): void {
        $index = array_search($metricReader, $this->metricReaders, true);
        if ($index === false) {
            return;
        }

        $metricProducer = $this->metricProducers[$index];

        unset(
            $this->metricReaders[$index],
            $this->metricProducers[$index],
        );

        foreach ($metricProducer->sources as $streamId => $sources) {
            foreach ($sources as $source) {
                $source->stream->unregister($source->reader);
                if (!$source->stream->hasReaders()) {
                    $this->registry->unregisterStream($streamId);
                }
            }
        }
    }

    public function getInstrument(Instrument $instrument, InstrumentationScope $instrumentationScope): ?RegisteredInstrument {
        $instrumentationScopeId = spl_object_id($instrumentationScope);
        $instrumentId = self::instrumentId($instrument);

        $r = $this->instruments[$instrumentationScopeId][$instrumentId] ?? null;
        if ($r?->instrument !== $instrument) {
            return null;
        }

        return $r;
    }

    public function createInstrument(Instrument $instrument, InstrumentationScope $instrumentationScope, MeterConfig $meterConfig): RegisteredInstrument {
        $instrumentationScopeId = spl_object_id($instrumentationScope);
        $instrumentId = self::instrumentId($instrument);

        self::ensureInstrumentNameValid($instrument, $instrumentationScopeId, $instrumentId);
        if ($r = $this->instruments[$instrumentationScopeId][$instrumentId] ?? null) {
            self::ensureIdentityInstrumentEquals($instrument, $instrumentationScopeId, $instrumentId, $r->instrument);
            return $r;
        }

        $instrumentName = self::instrumentName($instrument);
        self::ensureInstrumentNameNotConflicting($instrument, $instrumentationScopeId, $instrumentId, $instrumentName);
        self::acquireInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);

        if (!$meterConfig->disabled) {
            $this->startTimestamp ??= $this->clock->now();
            $this->createStreams($instrument, $instrumentationScope, $this->startTimestamp);
        }

        $stalenessHandler = $this->stalenessHandlerFactory->create();
        $stalenessHandler->onStale(fn() => $this->releaseStreams($instrument));
        $stalenessHandler->onStale(function() use ($instrumentationScopeId, $instrumentId, $instrumentName): void {
            unset($this->instruments[$instrumentationScopeId][$instrumentId]);
            if (!$this->instruments[$instrumentationScopeId]) {
                unset($this->instruments[$instrumentationScopeId]);
            }
            $this->releaseInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);
            $this->startTimestamp = null;
        });

        return $this->instruments[$instrumentationScopeId][$instrumentId] = new RegisteredInstrument(
            $instrument,
            $instrumentationScope,
            $stalenessHandler,
            $meterConfig->disabled,
        );
    }

    /**
     * @param array<int, array<int, list<Descriptor>>> $descriptors $streams
     */
    private function reconcileStreamSources(Instrument $instrument, array $descriptors): void {
        $streamIds = $this->registry->streams($instrument);

        foreach ($streamIds as $streamId) {
            $stream = $this->registry->stream($streamId);

            foreach ($this->metricReaders as $index => $metricReader) {
                $producer = $this->metricProducers[$index];
                $reusableSources = $producer->unregisterStream($streamId);

                foreach ($descriptors[$index][$streamId] ?? [] as $descriptor) {
                    foreach ($reusableSources as $i => $source) {
                        if (self::descriptorsEqual($descriptor, $source->descriptor)) {
                            $producer->registerMetricSource($streamId, $source);
                            unset($reusableSources[$i]);
                            continue 2;
                        }
                    }

                    $this->logger?->debug('Creating metric source', ['descriptor' => $descriptor, 'reader' => $index]);
                    $producer->registerMetricSource($streamId, new MetricStreamSource(
                        $descriptor,
                        $stream,
                        $stream->register($metricReader->resolveTemporality($descriptor->instrumentType)),
                    ));
                }

                foreach ($reusableSources as $source) {
                    $this->logger?->debug('Releasing metric source', ['descriptor' => $source->descriptor, 'reader' => $index]);
                    $source->stream->unregister($source->reader);
                }
            }

            if (!$stream->hasReaders()) {
                $this->registry->unregisterStream($streamId);
            }
        }
    }

    private function createStreams(Instrument $instrument, InstrumentationScope $instrumentationScope, int $startTimestamp): void {
        $descriptors = match ($instrument->type) {
            InstrumentType::Counter,
            InstrumentType::UpDownCounter,
            InstrumentType::Histogram,
            InstrumentType::Gauge,
                => $this->createSynchronousStreams($instrument, $instrumentationScope, $startTimestamp),
            InstrumentType::AsynchronousCounter,
            InstrumentType::AsynchronousUpDownCounter,
            InstrumentType::AsynchronousGauge,
                => $this->createAsynchronousStreams($instrument, $instrumentationScope, $startTimestamp),
        };

        $this->reconcileStreamSources($instrument, $descriptors);
    }

    private function createSynchronousStreams(Instrument $instrument, InstrumentationScope $instrumentationScope, int $startTimestamp): array {
        $descriptors = [];
        foreach ($this->views($instrument, $instrumentationScope) as $view) {
            $streamId = $this->registry->registerSynchronousStream(
                $instrument,
                new SynchronousMetricStream($view->aggregator, $startTimestamp, $view->cardinalityLimit),
                new DefaultMetricAggregator(
                    $view->aggregator,
                    $view->attributeProcessor,
                    $view->exemplarFilter,
                    $view->exemplarReservoir,
                    $view->cardinalityLimit,
                ),
            );

            $descriptors[$view->index][$streamId][] = $view->descriptor;
        }

        return $descriptors;
    }

    private function createAsynchronousStreams(Instrument $instrument, InstrumentationScope $instrumentationScope, int $startTimestamp): array {
        $descriptors = [];
        foreach ($this->views($instrument, $instrumentationScope) as $view) {
            $streamId = $this->registry->registerAsynchronousStream(
                $instrument,
                new AsynchronousMetricStream($view->aggregator, $startTimestamp),
                new DefaultMetricAggregatorFactory(
                    $view->aggregator,
                    $view->attributeProcessor,
                    $view->cardinalityLimit,
                ),
            );

            $descriptors[$view->index][$streamId][] = $view->descriptor;
        }

        return $descriptors;
    }

    private static function descriptorsEqual(Descriptor $left, Descriptor $right): bool {
        return $left->instrumentationScope === $right->instrumentationScope
            && $left->instrumentType === $right->instrumentType
            && $left->name === $right->name
            && $left->unit === $right->unit
            && $left->description === $right->description;
    }

    /**
     * @return iterable<ResolvedView>
     */
    private function views(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable {
        $attributeProcessor = new DefaultAttributeProcessor();
        if (($attributeKeys = $instrument->advisory['Attributes'] ?? null) !== null) {
            $attributeProcessor = new FilteredAttributeProcessor(Attributes::filterKeys(include: $attributeKeys));
        }

        foreach ($this->viewRegistry->find($instrument, $instrumentationScope) as $view) {
            $name = $view->name ?? $instrument->name;
            $description = $view->description ?? $instrument->description;
            $viewAttributeProcessor = match ($view->attributeKeys) {
                default => new FilteredAttributeProcessor($view->attributeKeys),
                null => $attributeProcessor,
            };

            $descriptor = new Descriptor(
                $instrumentationScope,
                $name,
                $instrument->unit,
                $description,
                $instrument->type,
            );

            $viewAggregator = $view->aggregation?->aggregator($instrument->type, $instrument->advisory);
            if (!$viewAggregator && $view->aggregation) {
                $this->logger?->warning('View aggregation "{aggregation}" incompatible with instrument type "{instrument_type}", dropping view "{view}"', [
                    'aggregation' => $view->aggregation,
                    'instrument_type' => $instrument->type,
                    'view' => $descriptor->name,
                ]);
                continue;
            }

            foreach ($this->metricReaders as $i => $metricReader) {
                $aggregator = $viewAggregator ?? $metricReader->resolveAggregation($instrument->type)->aggregator($instrument->type, $instrument->advisory);
                if (!$aggregator || $aggregator instanceof DropAggregator) {
                    continue;
                }

                $exemplarReservoir = $view->exemplarReservoir ?? $this->exemplarReservoir;
                $cardinalityLimit = $view->cardinalityLimit ?? $metricReader->resolveCardinalityLimit($instrument->type) ?? 2000;

                yield new ResolvedView(
                    $descriptor,
                    $viewAttributeProcessor,
                    $aggregator,
                    $this->exemplarFilter,
                    $exemplarReservoir,
                    $cardinalityLimit,
                    $i,
                );
            }
        }
    }

    private function releaseStreams(Instrument $instrument): void {
        foreach ($this->registry->unregisterStreams($instrument) as $streamId) {
            foreach ($this->metricProducers as $metricProducer) {
                $metricProducer->unregisterStream($streamId);
            }
        }
    }

    private function acquireInstrumentName(int $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        $this->logger?->debug('Creating instrument', [
            'name' => $instrumentName,
            'scope_hash' => $instrumentationScopeId,
            'instrument_hash' => bin2hex($instrumentId),
        ]);

        $this->instrumentIdentities[$instrumentationScopeId][$instrumentName] ??= 0;
        $this->instrumentIdentities[$instrumentationScopeId][$instrumentName]++;
    }

    private function releaseInstrumentName(int $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        $this->logger?->debug('Releasing instrument', [
            'name' => $instrumentName,
            'scope_hash' => $instrumentationScopeId,
            'instrument_hash' => bin2hex($instrumentId),
        ]);

        if (!--$this->instrumentIdentities[$instrumentationScopeId][$instrumentName]) {
            unset($this->instrumentIdentities[$instrumentationScopeId][$instrumentName]);
            if (!$this->instrumentIdentities[$instrumentationScopeId]) {
                unset($this->instrumentIdentities[$instrumentationScopeId]);
            }
        }
    }

    private function ensureInstrumentNameValid(Instrument $instrument, int $instrumentationScopeId, string $instrumentId): void {
        if (preg_match('#^[A-Za-z][A-Za-z0-9_./-]{0,254}$#', $instrument->name)) {
            return;
        }

        $this->logger?->warning('Invalid instrument name', [
            'name' => $instrument->name,
            'scope_hash' => $instrumentationScopeId,
            'instrument_hash' => bin2hex($instrumentId),
        ]);
    }

    private function ensureIdentityInstrumentEquals(Instrument $instrument, int $instrumentationScopeId, string $instrumentId, Instrument $registered): void {
        if ($instrument->equals($registered)) {
            return;
        }

        $this->logger?->warning('Instrument with same identity and differing non-identifying fields, using stream of first-seen instrument', [
            'scope_hash' => $instrumentationScopeId,
            'instrument_hash' => bin2hex($instrumentId),
            'first_seen' => $registered,
            'instrument' => $instrument,
        ]);
    }

    private function ensureInstrumentNameNotConflicting(Instrument $instrument, int $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        if (!isset($this->instrumentIdentities[$instrumentationScopeId][$instrumentName])) {
            return;
        }

        $this->logger?->warning('Instrument with same name but differing identifying fields, using new stream', [
            'name' => $instrumentName,
            'scope_hash' => $instrumentationScopeId,
            'instrument_hash' => bin2hex($instrumentId),
            'instrument' => $instrument,
        ]);
    }

    private static function instrumentName(Instrument $instrument): string {
        return strtolower($instrument->name);
    }

    private static function instrumentId(Instrument $instrument): string {
        return hash('xxh128', serialize([
            $instrument->type,
            strtolower($instrument->name),
            $instrument->unit,
            $instrument->description,
        ]), true);
    }
}
