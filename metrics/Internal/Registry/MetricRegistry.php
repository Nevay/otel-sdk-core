<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Registry;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Future;
use Closure;
use Exception;
use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\ContextResolver;
use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricAggregator;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricAggregatorFactory;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricStream;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use RuntimeException;
use function Amp\async;
use function count;

final class MetricRegistry implements MetricWriter, MetricCollector {

    private ?ContextStorageInterface $contextStorage;
    private AttributesFactory $attributesFactory;
    private Clock $clock;

    /** @var array<int, MetricStream> */
    private array $streams = [];
    /** @var array<int, MetricAggregator> */
    private array $synchronousAggregators = [];
    /** @var array<int, MetricAggregatorFactory> */
    private array $asynchronousAggregatorFactories = [];

    /** @var array<int, array<int, int>> */
    private array $instrumentToStreams = [];
    /** @var array<int, int> */
    private array $streamToInstrument = [];
    /** @var array<int, array<int, int>> */
    private array $instrumentToCallbacks = [];
    /** @var array<int, Closure> */
    private array $asynchronousCallbacks = [];
    /** @var array<int, list<int>> */
    private array $asynchronousCallbackArguments = [];

    public function __construct(
        ?ContextStorageInterface $contextStorage,
        AttributesFactory $attributesFactory,
        Clock $clock,
    ) {
        $this->contextStorage = $contextStorage;
        $this->attributesFactory = $attributesFactory;
        $this->clock = $clock;
    }

    public function registerSynchronousStream(Instrument $instrument, MetricStream $stream, MetricAggregator $aggregator): int {
        $this->streams[] = $stream;
        $streamId = array_key_last($this->streams);
        $instrumentId = spl_object_id($instrument);

        $this->synchronousAggregators[$streamId] = $aggregator;
        $this->instrumentToStreams[$instrumentId][$streamId] = $streamId;
        $this->streamToInstrument[$streamId] = $instrumentId;

        return $streamId;
    }

    public function registerAsynchronousStream(Instrument $instrument, MetricStream $stream, MetricAggregatorFactory $aggregatorFactory): int {
        $this->streams[] = $stream;
        $streamId = array_key_last($this->streams);
        $instrumentId = spl_object_id($instrument);

        $this->asynchronousAggregatorFactories[$streamId] = $aggregatorFactory;
        $this->instrumentToStreams[$instrumentId][$streamId] = $streamId;
        $this->streamToInstrument[$streamId] = $instrumentId;

        return $streamId;
    }

    public function unregisterStream(int $streamId): void {
        $instrumentId = $this->streamToInstrument[$streamId];
        unset(
            $this->streams[$streamId],
            $this->synchronousAggregators[$streamId],
            $this->asynchronousAggregatorFactories[$streamId],
            $this->instrumentToStreams[$instrumentId][$streamId],
            $this->streamToInstrument[$streamId],
        );
        if (!$this->instrumentToStreams[$instrumentId]) {
            unset($this->instrumentToStreams[$instrumentId]);
        }
    }

    public function record(Instrument $instrument, float|int $value, iterable $attributes = [], ContextInterface|false|null $context = null): void {
        $context = ContextResolver::resolve($context, $this->contextStorage);
        $attributes = $this->attributesFactory->builder()->addAll($attributes)->build();
        $timestamp = $this->clock->now();
        $instrumentId = spl_object_id($instrument);
        foreach ($this->instrumentToStreams[$instrumentId] ?? [] as $streamId) {
            if ($aggregator = $this->synchronousAggregators[$streamId] ?? null) {
                $aggregator->record($value, $attributes, $context, $timestamp);
            }
        }
    }

    public function registerCallback(Closure $callback, Instrument $instrument, Instrument ...$instruments): int {
        $this->asynchronousCallbacks[] = $callback;
        $callbackId = array_key_last($this->asynchronousCallbacks);

        $instrumentId = spl_object_id($instrument);
        $this->asynchronousCallbackArguments[$callbackId] = [$instrumentId];
        $this->instrumentToCallbacks[$instrumentId][$callbackId] = $callbackId;
        foreach ($instruments as $instrument) {
            $instrumentId = spl_object_id($instrument);
            $this->asynchronousCallbackArguments[$callbackId][] = $instrumentId;
            $this->instrumentToCallbacks[$instrumentId][$callbackId] = $callbackId;
        }

        return $callbackId;
    }

    public function unregisterCallback(int $callbackId): void {
        $instrumentIds = $this->asynchronousCallbackArguments[$callbackId];
        unset(
            $this->asynchronousCallbacks[$callbackId],
            $this->asynchronousCallbackArguments[$callbackId],
        );
        foreach ($instrumentIds as $instrumentId) {
            unset($this->instrumentToCallbacks[$instrumentId][$callbackId]);
            if (!$this->instrumentToCallbacks[$instrumentId]) {
                unset($this->instrumentToCallbacks[$instrumentId]);
            }
        }
    }

    public function collectAndPush(iterable $streamIds, ?Cancellation $cancellation = null): int {
        $timestamp = $this->clock->now();
        $aggregators = [];
        $observers = [];
        $callbacks = [];
        foreach ($streamIds as $streamId) {
            if (!$aggregator = $this->synchronousAggregators[$streamId] ?? null) {
                $aggregator = $this->asynchronousAggregatorFactories[$streamId]->create();

                $instrumentId = $this->streamToInstrument[$streamId];
                $observers[$instrumentId] ??= new MultiObserver($this->attributesFactory, $timestamp);
                $observers[$instrumentId]->writers[] = $aggregator;
                foreach ($this->instrumentToCallbacks[$instrumentId] ?? [] as $callbackId) {
                    $callbacks[$callbackId] ??= $this->asynchronousCallbackArguments[$callbackId];
                }
            }

            $aggregators[$streamId] = $aggregator;
        }
        $noopObserver = new NoopObserver();
        $handler = function(int $callbackId, array $callbackArguments) use ($observers, $noopObserver): void {
            if (!$callback = $this->asynchronousCallbacks[$callbackId] ?? null) {
                throw new RuntimeException('Callback unregistered during metric collection');
            }

            $args = [];
            foreach ($callbackArguments as $instrumentId) {
                $args[] = $observers[$instrumentId] ?? $noopObserver;
            }

            $callback(...$args);
        };
        $futures = [];
        foreach ($callbacks as $callbackId => $callbackArguments) {
            $futures[$callbackId] = async($handler, $callbackId, $callbackArguments);
        }
        try {
            foreach (Future::iterate($futures, $cancellation) as $callbackId => $future) {
                try {
                    $future->await();
                    unset($callbacks[$callbackId]);
                } catch (Exception) {}
            }
        } catch (CancelledException) {}

        foreach ($callbacks as $callbackArguments) {
            foreach ($callbackArguments as $instrumentId) {
                foreach ($this->instrumentToStreams[$instrumentId] ?? [] as $streamId) {
                    unset($aggregators[$streamId]);
                }
            }
        }

        $timestamp = $this->clock->now();
        foreach ($aggregators as $streamId => $aggregator) {
            if ($stream = $this->streams[$streamId] ?? null) {
                $stream->push($aggregator->collect($timestamp));
            }
        }

        return count($aggregators);
    }
}
