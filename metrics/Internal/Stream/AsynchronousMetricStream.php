<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use function array_search;
use function assert;
use function count;

/**
 * @template TSummary
 * @template-covariant TData of Data
 * @implements MetricStream<TSummary, TData>
 *
 * @internal
 */
final class AsynchronousMetricStream implements MetricStream {

    /** @var Aggregator<TSummary, TData, DataPoint> */
    private Aggregator $aggregator;
    private int $startTimestamp;
    /** @var Metric<TSummary> */
    private Metric $metric;

    /** @var array<int, Metric<TSummary>|null> */
    private array $lastReads = [];
    private int $cumulativeReaders = 0;

    /**
     * @param Aggregator<TSummary, TData, DataPoint> $aggregation
     */
    public function __construct(Aggregator $aggregation, int $startTimestamp) {
        $this->aggregator = $aggregation;
        $this->startTimestamp = $startTimestamp;
        $this->metric = new Metric([], $startTimestamp);
    }

    public function temporality(): Temporality {
        return Temporality::Cumulative;
    }

    public function timestamp(): int {
        return $this->metric->timestamp;
    }

    public function push(Metric $metric): void {
        $this->metric = $metric;
    }

    public function register(Temporality $temporality): int {
        if ($temporality === Temporality::Cumulative) {
            $this->cumulativeReaders++;
            return -1;
        }

        if (($reader = array_search(null, $this->lastReads, true)) === false) {
            $reader = count($this->lastReads);
        }

        $this->lastReads[$reader] = $this->metric;

        return $reader;
    }

    public function unregister(int $reader): void {
        if (!isset($this->lastReads[$reader])) {
            assert($this->cumulativeReaders > 0);
            $this->cumulativeReaders--;
            return;
        }

        $this->lastReads[$reader] = null;
    }

    public function hasReaders(): bool {
        return $this->lastReads || $this->cumulativeReaders;
    }

    public function collect(int $reader): Data {
        $metric = $this->metric;

        if (($lastRead = $this->lastReads[$reader] ?? null) === null) {
            $temporality = Temporality::Cumulative;
            $startTimestamp = $this->startTimestamp;
        } else {
            $temporality = Temporality::Delta;
            $startTimestamp = $lastRead->timestamp;
            $this->lastReads[$reader] = $metric;
        }

        $dataPoints = [];
        foreach ($metric->metricPoints as $index => $metricPoint) {
            $summary = ($last = $lastRead->metricPoints[$index] ?? null)
                ? $this->aggregator->diff($last->summary, $metricPoint->summary)
                : $metricPoint->summary;

            $dataPoints[] = $this->aggregator->toDataPoint(
                $metricPoint->attributes,
                $summary,
                $metricPoint->exemplars,
                $startTimestamp,
                $metric->timestamp,
            );
        }

        return $this->aggregator->toData(
            $dataPoints,
            $temporality,
        );
    }
}
