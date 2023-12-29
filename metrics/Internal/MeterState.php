<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal;

use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Metrics\AttributeProcessor\FilteredAttributeProcessor;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\Internal\Instrument\ObservableCallbackDestructor;
use Nevay\OtelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OtelSDK\Metrics\Internal\Stream\AsynchronousMetricStream;
use Nevay\OtelSDK\Metrics\Internal\Stream\DefaultMetricAggregator;
use Nevay\OtelSDK\Metrics\Internal\Stream\DefaultMetricAggregatorFactory;
use Nevay\OtelSDK\Metrics\Internal\Stream\SynchronousMetricStream;
use Nevay\OtelSDK\Metrics\Internal\View\ResolvedView;
use Nevay\OtelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OtelSDK\Metrics\View;
use Psr\Log\LoggerInterface;
use Throwable;
use WeakMap;
use function array_keys;
use function assert;
use function md5;
use function preg_match;
use function serialize;
use function spl_object_hash;
use function strtolower;

final class MeterState {

    private ?int $startTimestamp = null;

    /** @var array<string, array<string, array{Instrument, ReferenceCounter, WeakMap<object, ObservableCallbackDestructor>}>> */
    private array $asynchronous = [];
    /** @var array<string, array<string, array{Instrument, ReferenceCounter}>> */
    private array $synchronous = [];
    /** @var array<string, array<string, int>> */
    private array $instrumentIdentities = [];

    /**
     * @param iterable<MeterMetricProducer> $producers
     */
    public function __construct(
        public readonly MetricRegistry $registry,
        private readonly Resource $resource,
        private readonly Clock $clock,
        private readonly iterable $producers,
        private readonly ViewRegistry $viewRegistry,
        private readonly StalenessHandlerFactory $stalenessHandlerFactory,
        public readonly ?LoggerInterface $logger,
    ) {}

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

        $stalenessHandler = $this->stalenessHandlerFactory->create();
        $this->startTimestamp ??= $this->clock->now();
        $streams = [];
        $dedup = [];
        foreach ($this->views($instrument, $instrumentationScope, Temporality::Delta) as $view) {
            $dedupId = self::streamDedupId($view);
            if (($streamId = $dedup[$dedupId] ?? null) === null) {
                $stream = new SynchronousMetricStream($view->aggregation, $this->startTimestamp, $view->cardinalityLimit);
                assert($stream->temporality() === $view->descriptor->temporality);

                $streamId = $this->registry->registerSynchronousStream($instrument, $stream, new DefaultMetricAggregator(
                    $view->aggregation,
                    $view->attributeProcessor,
                    $view->exemplarReservoirFactory,
                    $view->cardinalityLimit,
                ));

                $streams[$streamId] = $stream;
                $dedup[$dedupId] = $streamId;
            }
            $view->metricProducer->registerMetricSource($streamId, $view->descriptor, $streams[$streamId], $view->temporality);
        }

        $streamIds = array_keys($streams);
        $stalenessHandler->onStale(function() use ($instrumentationScopeId, $instrumentId, $instrumentName, $streamIds): void {
            unset($this->synchronous[$instrumentationScopeId][$instrumentId]);
            if (!$this->synchronous[$instrumentationScopeId]) {
                unset($this->synchronous[$instrumentationScopeId]);
            }
            $this->releaseInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);
            $this->releaseStreams($streamIds);
        });

        return $this->synchronous[$instrumentationScopeId][$instrumentId] = [
            $instrument,
            $stalenessHandler,
        ];
    }

    /**
     * @return array{Instrument, ReferenceCounter, WeakMap<object, ObservableCallbackDestructor>}
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

        $stalenessHandler = $this->stalenessHandlerFactory->create();
        $this->startTimestamp ??= $this->clock->now();
        $streams = [];
        $dedup = [];
        foreach ($this->views($instrument, $instrumentationScope, Temporality::Cumulative) as $view) {
            $dedupId = self::streamDedupId($view);
            if (($streamId = $dedup[$dedupId] ?? null) === null) {
                $stream = new AsynchronousMetricStream($view->aggregation, $this->startTimestamp);
                assert($stream->temporality() === $view->descriptor->temporality);

                $streamId = $this->registry->registerAsynchronousStream($instrument, $stream, new DefaultMetricAggregatorFactory(
                    $view->aggregation,
                    $view->attributeProcessor,
                    $view->cardinalityLimit,
                ));

                $streams[$streamId] = $stream;
                $dedup[$dedupId] = $streamId;
            }
            $view->metricProducer->registerMetricSource($streamId, $view->descriptor, $streams[$streamId], $view->temporality);
        }

        $streamIds = array_keys($streams);
        $stalenessHandler->onStale(function() use ($instrumentationScopeId, $instrumentId, $instrumentName, $streamIds): void {
            unset($this->asynchronous[$instrumentationScopeId][$instrumentId]);
            if (!$this->asynchronous[$instrumentationScopeId]) {
                unset($this->asynchronous[$instrumentationScopeId]);
            }
            $this->releaseInstrumentName($instrumentationScopeId, $instrumentId, $instrumentName);
            $this->releaseStreams($streamIds);
        });

        return $this->asynchronous[$instrumentationScopeId][$instrumentId] = [
            $instrument,
            $stalenessHandler,
            new WeakMap(),
        ];
    }

    /**
     * @return iterable<ResolvedView>
     */
    private function views(Instrument $instrument, InstrumentationScope $instrumentationScope, Temporality $streamTemporality): iterable {
        $views = $this->viewRegistry->find($instrument, $instrumentationScope) ?? [new View()];

        $attributeProcessor = null;
        if ($attributeKeys = $instrument->advisory['Attributes'] ?? null) {
            $attributeProcessor = new FilteredAttributeProcessor($attributeKeys);
        }

        foreach ($views as $view) {
            if ($view->aggregationResolver === false) {
                continue;
            }

            $name = $view->name ?? $instrument->name;
            $unit = $view->unit ?? $instrument->unit ?: null;
            $description = $view->description ?? $instrument->description ?: null;
            $attributeProcessor = $view->attributeProcessor ?? $attributeProcessor ?: null;

            $descriptor = new Descriptor(
                $this->resource,
                $instrumentationScope,
                $name,
                $unit,
                $description,
                $instrument->type,
                $streamTemporality,
            );

            foreach ($this->producers as $producer) {
                if (!$producerTemporality = $producer->temporalityResolver->resolveTemporality($descriptor)) {
                    continue;
                }

                $aggregationResolver = $view->aggregationResolver ?? $producer->aggregationResolver ?: null;
                $exemplarReservoirResolver = $view->exemplarReservoirResolver ?? $producer->exemplarReservoirResolver ?: null;
                $cardinalityLimitResolver = $view->cardinalityLimitResolver ?? $producer->cardinalityLimitResolver ?: null;

                if (!$aggregation = $aggregationResolver?->resolveAggregation($instrument->type, $instrument->advisory)) {
                    continue;
                }

                $exemplarReservoirFactory = $exemplarReservoirResolver?->resolveExemplarReservoir($aggregation);
                $cardinalityLimit = $cardinalityLimitResolver?->resolveCardinalityLimit($instrument->type);

                yield new ResolvedView(
                    $descriptor,
                    $attributeProcessor,
                    $aggregation,
                    $exemplarReservoirFactory,
                    $cardinalityLimit,
                    $producer,
                    $producerTemporality,
                );
            }
        }
    }

    private function releaseStreams(array $streamIds): void {
        $this->startTimestamp = null;
        foreach ($streamIds as $streamId) {
            $this->registry->unregisterStream($streamId);
            foreach ($this->producers as $producer) {
                $producer->unregisterStream($streamId);
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
            'first-seen' => $registered,
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
            . self::serialize($view->aggregation)
            . self::serialize($view->exemplarReservoirFactory)
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
