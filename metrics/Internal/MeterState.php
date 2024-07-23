<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Closure;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\DropAggregator;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\DefaultAttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\FilteredAttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use Nevay\OTelSDK\Metrics\Internal\Instrument\ObservableCallbackDestructor;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\Stream\AsynchronousMetricStream;
use Nevay\OTelSDK\Metrics\Internal\Stream\DefaultMetricAggregator;
use Nevay\OTelSDK\Metrics\Internal\Stream\DefaultMetricAggregatorFactory;
use Nevay\OTelSDK\Metrics\Internal\Stream\SynchronousMetricStream;
use Nevay\OTelSDK\Metrics\Internal\View\ResolvedView;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OTelSDK\Metrics\MetricReader;
use Nevay\OTelSDK\Metrics\View;
use Psr\Log\LoggerInterface;
use Throwable;
use WeakMap;
use function assert;
use function md5;
use function preg_match;
use function serialize;
use function spl_object_hash;
use function strtolower;

/**
 * @internal
 */
final class MeterState {

    private ?int $startTimestamp = null;

    /** @var array<string, true> */
    private array $disabledInstrumentationScopes = [];

    /** @var array<string, array<string, array{Instrument, ReferenceCounter, InstrumentationScope}>> */
    private array $asynchronous = [];
    /** @var array<string, array<string, array{Instrument, ReferenceCounter, InstrumentationScope}>> */
    private array $synchronous = [];
    /** @var array<string, array<string, int>> */
    private array $instrumentIdentities = [];

    /**
     * @param array<MetricReader> $metricReaders
     * @param array<MeterMetricProducer> $metricProducers
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     */
    public function __construct(
        public readonly MetricRegistry $registry,
        private readonly Resource $resource,
        private readonly Clock $clock,
        public readonly array $metricReaders,
        private readonly array $metricProducers,
        private readonly ExemplarFilter $exemplarFilter,
        private readonly Closure $exemplarReservoir,
        private readonly ViewRegistry $viewRegistry,
        private readonly StalenessHandlerFactory $stalenessHandlerFactory,
        public readonly WeakMap $destructors,
        public readonly ?LoggerInterface $logger,
    ) {}

    public function enableInstrumentationScope(InstrumentationScope $instrumentationScope): bool {
        $instrumentationScopeId = self::instrumentationScopeId($instrumentationScope);
        if (!isset($this->disabledInstrumentationScopes[$instrumentationScopeId])) {
            return false;
        }

        $this->logger?->debug('Enabling instruments of instrumentation scope', ['scope' => $instrumentationScope]);
        unset($this->disabledInstrumentationScopes[$instrumentationScopeId]);

        $startTimestamp = $this->clock->now();
        foreach ($this->asynchronous[$instrumentationScopeId] ?? [] as [$instrument]) {
            $this->createAsynchronousStreams($instrument, $instrumentationScope, $startTimestamp);
        }
        foreach ($this->synchronous[$instrumentationScopeId] ?? [] as [$instrument]) {
            $this->createSynchronousStreams($instrument, $instrumentationScope, $startTimestamp);
        }

        return true;
    }

    public function disableInstrumentationScope(InstrumentationScope $instrumentationScope): bool {
        $instrumentationScopeId = self::instrumentationScopeId($instrumentationScope);
        if (isset($this->disabledInstrumentationScopes[$instrumentationScopeId])) {
            return false;
        }

        $this->logger?->debug('Disabling instruments of instrumentation scope', ['scope' => $instrumentationScope]);
        $this->disabledInstrumentationScopes[$instrumentationScopeId] = true;

        foreach ($this->asynchronous[$instrumentationScopeId] ?? [] as [$instrument]) {
            $this->releaseStreams($instrument);
        }
        foreach ($this->synchronous[$instrumentationScopeId] ?? [] as [$instrument]) {
            $this->releaseStreams($instrument);
        }

        return true;
    }

    /**
     * @return array{Instrument, ReferenceCounter}|null
     */
    public function getAsynchronousInstrument(Instrument $instrument, InstrumentationScope $instrumentationScope): ?array {
        $instrumentationScopeId = self::instrumentationScopeId($instrumentationScope);
        $instrumentId = self::instrumentId($instrument);

        $asynchronousInstrument = $this->asynchronous[$instrumentationScopeId][$instrumentId] ?? null;
        if (!$asynchronousInstrument || $asynchronousInstrument[0] !== $instrument) {
            return null;
        }

        return $asynchronousInstrument;
    }

    /**
     * @return array{Instrument, ReferenceCounter}
     */
    public function createSynchronousInstrument(Instrument $instrument, InstrumentationScope $instrumentationScope): array {
        $instrumentationScopeId = self::instrumentationScopeId($instrumentationScope);
        $instrumentId = self::instrumentId($instrument);

        self::ensureInstrumentNameValid($instrument, $instrumentationScopeId, $instrumentId);
        if ($synchronousInstrument = $this->synchronous[$instrumentationScopeId][$instrumentId] ?? null) {
            self::ensureIdentityInstrumentEquals($instrument, $instrumentationScopeId, $instrumentId, $synchronousInstrument[0]);
            return $synchronousInstrument;
        }

        $instrumentName = self::instrumentName($instrument);
        self::ensureInstrumentNameNotConflicting($instrument, $instrumentationScopeId, $instrumentId, $instrumentName);
        self::acquireInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);

        if (!isset($this->disabledInstrumentationScopes[$instrumentationScopeId])) {
            $this->startTimestamp ??= $this->clock->now();
            $this->createSynchronousStreams($instrument, $instrumentationScope, $this->startTimestamp);
        }

        $stalenessHandler = $this->stalenessHandlerFactory->create();
        $stalenessHandler->onStale(fn() => $this->releaseStreams($instrument));
        $stalenessHandler->onStale(function() use ($instrumentationScopeId, $instrumentId, $instrumentName): void {
            unset($this->synchronous[$instrumentationScopeId][$instrumentId]);
            if (!$this->synchronous[$instrumentationScopeId]) {
                unset($this->synchronous[$instrumentationScopeId]);
            }
            $this->releaseInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);
            $this->startTimestamp = null;
        });

        return $this->synchronous[$instrumentationScopeId][$instrumentId] = [
            $instrument,
            $stalenessHandler,
            $instrumentationScope, // keep reference alive
        ];
    }

    /**
     * @return array{Instrument, ReferenceCounter}
     */
    public function createAsynchronousInstrument(Instrument $instrument, InstrumentationScope $instrumentationScope): array {
        $instrumentationScopeId = self::instrumentationScopeId($instrumentationScope);
        $instrumentId = self::instrumentId($instrument);

        self::ensureInstrumentNameValid($instrument, $instrumentationScopeId, $instrumentId);
        if ($asynchronousInstrument = $this->asynchronous[$instrumentationScopeId][$instrumentId] ?? null) {
            self::ensureIdentityInstrumentEquals($instrument, $instrumentationScopeId, $instrumentId, $asynchronousInstrument[0]);
            return $asynchronousInstrument;
        }

        $instrumentName = self::instrumentName($instrument);
        self::ensureInstrumentNameNotConflicting($instrument, $instrumentationScopeId, $instrumentId, $instrumentName);
        self::acquireInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);

        if (!isset($this->disabledInstrumentationScopes[$instrumentationScopeId])) {
            $this->startTimestamp ??= $this->clock->now();
            $this->createAsynchronousStreams($instrument, $instrumentationScope, $this->startTimestamp);
        }

        $stalenessHandler = $this->stalenessHandlerFactory->create();
        $stalenessHandler->onStale(fn() => $this->releaseStreams($instrument));
        $stalenessHandler->onStale(function() use ($instrumentationScopeId, $instrumentId, $instrumentName): void {
            unset($this->asynchronous[$instrumentationScopeId][$instrumentId]);
            if (!$this->asynchronous[$instrumentationScopeId]) {
                unset($this->asynchronous[$instrumentationScopeId]);
            }
            $this->releaseInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);
            $this->startTimestamp = null;
        });

        return $this->asynchronous[$instrumentationScopeId][$instrumentId] = [
            $instrument,
            $stalenessHandler,
            $instrumentationScope, // keep reference alive
        ];
    }

    private function createSynchronousStreams(Instrument $instrument, InstrumentationScope $instrumentationScope, int $startTimestamp): void {
        $streams = [];
        $dedup = [];
        foreach ($this->views($instrument, $instrumentationScope, Temporality::Delta) as $view) {
            $dedupId = self::streamDedupId($view);
            if (($streamId = $dedup[$dedupId] ?? null) === null) {
                $stream = new SynchronousMetricStream($view->aggregator, $startTimestamp, $view->cardinalityLimit);
                assert($stream->temporality() === $view->descriptor->temporality);

                $streamId = $this->registry->registerSynchronousStream($instrument, $stream, new DefaultMetricAggregator(
                    $view->aggregator,
                    $view->attributeProcessor,
                    $view->exemplarFilter,
                    $view->exemplarReservoir,
                    $view->cardinalityLimit,
                ));

                $streams[$streamId] = $stream;
                $dedup[$dedupId] = $streamId;
            }
            $stream = $streams[$streamId];
            $source = new MetricStreamSource($view->descriptor, $stream, $stream->register($view->temporality));
            $view->metricProducer->registerMetricSource($streamId, $source);
        }
    }

    private function createAsynchronousStreams(Instrument $instrument, InstrumentationScope $instrumentationScope, int $startTimestamp): void {
        $streams = [];
        $dedup = [];
        foreach ($this->views($instrument, $instrumentationScope, Temporality::Cumulative) as $view) {
            $dedupId = self::streamDedupId($view);
            if (($streamId = $dedup[$dedupId] ?? null) === null) {
                $stream = new AsynchronousMetricStream($view->aggregator, $startTimestamp);
                assert($stream->temporality() === $view->descriptor->temporality);

                $streamId = $this->registry->registerAsynchronousStream($instrument, $stream, new DefaultMetricAggregatorFactory(
                    $view->aggregator,
                    $view->attributeProcessor,
                    $view->cardinalityLimit,
                ));

                $streams[$streamId] = $stream;
                $dedup[$dedupId] = $streamId;
            }
            $stream = $streams[$streamId];
            $source = new MetricStreamSource($view->descriptor, $stream, $stream->register($view->temporality));
            $view->metricProducer->registerMetricSource($streamId, $source);
        }
    }

    /**
     * @return iterable<ResolvedView>
     */
    private function views(Instrument $instrument, InstrumentationScope $instrumentationScope, Temporality $streamTemporality): iterable {
        $views = $this->viewRegistry->find($instrument, $instrumentationScope) ?? [new View()];

        $attributeProcessor = new DefaultAttributeProcessor();
        if (($attributeKeys = $instrument->advisory['Attributes'] ?? null) !== null) {
            $attributeProcessor = new FilteredAttributeProcessor($attributeKeys);
        }

        foreach ($views as $view) {
            $name = $view->name ?? $instrument->name;
            $description = $view->description ?? $instrument->description;
            $viewAttributeProcessor = match ($view->attributeKeys) {
                default => new FilteredAttributeProcessor($view->attributeKeys),
                null => $attributeProcessor,
            };

            $descriptor = new Descriptor(
                $this->resource,
                $instrumentationScope,
                $name,
                $instrument->unit,
                $description,
                $instrument->type,
                $streamTemporality,
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
                if (!$producerTemporality = $metricReader->resolveTemporality($descriptor)) {
                    continue;
                }

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
                    $this->metricProducers[$i],
                    $producerTemporality,
                );
            }
        }
    }

    private function releaseStreams(Instrument $instrument): void {
        if (!$streamIds = $this->registry->unregisterStreams($instrument)) {
            return;
        }

        foreach ($streamIds as $streamId) {
            foreach ($this->metricProducers as $metricProducer) {
                $metricProducer->unregisterStream($streamId);
            }
        }
    }

    private function acquireInstrumentName(string $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        $this->logger?->debug('Creating metric stream for instrument', [
            'name' => $instrumentName,
            'scope_hash' => md5($instrumentationScopeId),
            'instrument_hash' => md5($instrumentId),
        ]);

        $this->instrumentIdentities[$instrumentationScopeId][$instrumentName] ??= 0;
        $this->instrumentIdentities[$instrumentationScopeId][$instrumentName]++;
    }

    private function releaseInstrumentName(string $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        $this->logger?->debug('Releasing metric stream for instrument', [
            'name' => $instrumentName,
            'scope_hash' => md5($instrumentationScopeId),
            'instrument_hash' => md5($instrumentId),
        ]);

        if (!--$this->instrumentIdentities[$instrumentationScopeId][$instrumentName]) {
            unset($this->instrumentIdentities[$instrumentationScopeId][$instrumentName]);
            if (!$this->instrumentIdentities[$instrumentationScopeId]) {
                unset($this->instrumentIdentities[$instrumentationScopeId]);
            }
        }
    }

    private function ensureInstrumentNameValid(Instrument $instrument, string $instrumentationScopeId, string $instrumentId): void {
        if (preg_match('#^[A-Za-z][A-Za-z0-9_./-]{0,254}$#', $instrument->name)) {
            return;
        }

        $this->logger?->warning('Invalid instrument name', [
            'name' => $instrument->name,
            'scope_hash' => md5($instrumentationScopeId),
            'instrument_hash' => md5($instrumentId),
        ]);
    }

    private function ensureIdentityInstrumentEquals(Instrument $instrument, string $instrumentationScopeId, string $instrumentId, Instrument $registered): void {
        if ($instrument->equals($registered)) {
            return;
        }

        $this->logger?->warning('Instrument with same identity and differing non-identifying fields, using stream of first-seen instrument', [
            'scope_hash' => md5($instrumentationScopeId),
            'instrument_hash' => md5($instrumentId),
            'first_seen' => $registered,
            'instrument' => $instrument,
        ]);
    }

    private function ensureInstrumentNameNotConflicting(Instrument $instrument, string $instrumentationScopeId, string $instrumentId, string $instrumentName): void {
        if (!isset($this->instrumentIdentities[$instrumentationScopeId][$instrumentName])) {
            return;
        }

        $this->logger?->warning('Instrument with same name but differing identifying fields, using new stream', [
            'name' => $instrumentName,
            'scope_hash' => md5($instrumentationScopeId),
            'instrument_hash' => md5($instrumentId),
            'instrument' => $instrument,
        ]);
    }

    private static function instrumentName(Instrument $instrument): string {
        return strtolower($instrument->name);
    }

    private static function instrumentId(Instrument $instrument): string {
        return serialize([
            $instrument->type,
            strtolower($instrument->name),
            $instrument->unit,
            $instrument->description,
        ]);
    }

    private static function instrumentationScopeId(InstrumentationScope $instrumentationScope): string {
        static $cache = new WeakMap();
        return $cache[$instrumentationScope] ??= serialize([
            $instrumentationScope->name,
            $instrumentationScope->version,
            $instrumentationScope->schemaUrl,
        ]);
    }

    private static function streamDedupId(ResolvedView $view): string {
        return ''
            . self::serialize($view->attributeProcessor)
            . self::serialize($view->aggregator)
            . self::serialize(($view->exemplarReservoir)($view->aggregator))
            . $view->cardinalityLimit
        ;
    }

    private static function serialize(?object $object): string {
        try {
            return serialize($object);
        } catch (Throwable) {}

        assert($object !== null);

        return spl_object_hash($object);
    }
}
