<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal;

use Amp\Cancellation;
use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Metric;
use Nevay\OtelSDK\Metrics\ExemplarReservoirResolver;
use Nevay\OtelSDK\Metrics\Internal\Registry\MetricCollector;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricStream;
use Nevay\OtelSDK\Metrics\MetricProducer;
use Nevay\OtelSDK\Metrics\TemporalityResolver;
use function array_keys;

final class MeterMetricProducer implements MetricProducer {

    private readonly MetricCollector $collector;
    private readonly TemporalityResolver $temporalityResolver;
    public readonly AggregationResolver $aggregationResolver;
    public readonly ExemplarReservoirResolver $exemplarReservoirResolver;

    /** @var array<int, list<MetricStreamSource>> */
    private array $sources = [];
    private ?array $streamIds = null;

    public function __construct(MetricCollector $collector, TemporalityResolver $temporalityResolver, AggregationResolver $aggregationResolver, ExemplarReservoirResolver $exemplarReservoirResolver) {
        $this->collector = $collector;
        $this->temporalityResolver = $temporalityResolver;
        $this->aggregationResolver = $aggregationResolver;
        $this->exemplarReservoirResolver = $exemplarReservoirResolver;
    }

    public function unregisterStream(int $streamId): void {
        unset($this->sources[$streamId]);
        $this->streamIds = null;
    }

    public function registerMetricSource(int $streamId, Descriptor $descriptor, MetricStream $stream): void {
        if (!$temporality = $this->temporalityResolver->resolveTemporality($descriptor)) {
            return;
        }

        $this->sources[$streamId][] = new MetricStreamSource(
            $descriptor,
            $stream,
            $stream->register($temporality),
        );
        $this->streamIds = null;
    }

    public function produce(?Cancellation $cancellation = null): iterable {
        $this->streamIds ??= array_keys($this->sources);
        $this->collector->collectAndPush($this->streamIds, $cancellation);

        foreach ($this->sources as $sources) {
            foreach ($sources as $source) {
                yield new Metric(
                    $source->descriptor,
                    $source->stream->collect($source->reader),
                );
            }
        }
    }
}
