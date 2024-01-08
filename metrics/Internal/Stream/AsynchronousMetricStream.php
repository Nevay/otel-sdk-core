<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use function array_search;
use function count;

/**
 * @template TSummary
 * @template-covariant TData of Data
 * @implements MetricStream<TSummary, TData>
 */
final class AsynchronousMetricStream implements MetricStream {

    /** @var Aggregation<TSummary, TData> */
    private Aggregation $aggregation;
    private int $startTimestamp;
    /** @var Metric<TSummary> */
    private Metric $metric;

    /** @var array<int, Metric<TSummary>|null> */
    private array $lastReads = [];

    /**
     * @param Aggregation<TSummary, TData> $aggregation
     */
    public function __construct(Aggregation $aggregation, int $startTimestamp) {
        $this->aggregation = $aggregation;
        $this->startTimestamp = $startTimestamp;
        $this->metric = new Metric([], [], $startTimestamp);
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
            return;
        }

        $this->lastReads[$reader] = null;
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
            $metric = $this->diff($lastRead, $metric);
        }

        return $this->aggregation->toData(
            $metric->attributes,
            $metric->summaries,
            $metric->exemplars,
            $startTimestamp,
            $metric->timestamp,
            $temporality,
        );
    }

    private function diff(Metric $lastRead, Metric $metric): Metric {
        $diff = clone $metric;
        foreach ($metric->summaries as $k => $summary) {
            if (!isset($lastRead->summaries[$k])) {
                continue;
            }

            $diff->summaries[$k] = $this->aggregation->diff($lastRead->summaries[$k], $summary);
        }

        return $diff;
    }
}
