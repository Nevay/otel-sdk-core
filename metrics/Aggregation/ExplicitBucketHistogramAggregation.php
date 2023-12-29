<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Aggregation;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Histogram;
use Nevay\OtelSDK\Metrics\Data\HistogramDataPoint;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;
use function array_fill;
use function count;
use const INF;
use const NAN;

/**
 * @implements Aggregation<ExplicitBucketHistogramSummary, Histogram>
 */
final class ExplicitBucketHistogramAggregation implements Aggregation {

    /**
     * @param list<float|int> $boundaries strictly ascending histogram bucket boundaries
     */
    public function __construct(
        public readonly array $boundaries,
        public readonly bool $recordMinMax = true,
    ) {}

    public function initialize(): ExplicitBucketHistogramSummary {
        return new ExplicitBucketHistogramSummary(
            0,
            0,
            $this->recordMinMax ? +INF : NAN,
            $this->recordMinMax ? -INF : NAN,
            array_fill(0, count($this->boundaries) + 1, 0),
        );
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        for ($i = 0, $n = count($this->boundaries); $i < $n && $this->boundaries[$i] < $value; $i++) {}
        $summary->count++;
        $summary->sum += $value;
        $summary->min = self::min($value, $summary->min);
        $summary->max = self::max($value, $summary->max);
        $summary->buckets[$i]++;
    }

    public function merge(mixed $left, mixed $right): ExplicitBucketHistogramSummary {
        $count = $right->count + $left->count;
        $sum = $right->sum + $left->sum;
        $min = self::min($right->min, $left->min);
        $max = self::max($right->max, $left->max);
        $buckets = $right->buckets;
        foreach ($left->buckets as $i => $bucketCount) {
            $buckets[$i] += $bucketCount;
        }

        return new ExplicitBucketHistogramSummary(
            $count,
            $sum,
            $min,
            $max,
            $buckets,
        );
    }

    public function diff(mixed $left, mixed $right): ExplicitBucketHistogramSummary {
        $count = $right->count - $left->count;
        $sum = $right->sum - $left->sum;
        $min = $right->min <= $left->min ? $right->min : NAN;
        $max = $right->max >= $left->max ? $right->max : NAN;
        $buckets = $right->buckets;
        foreach ($left->buckets as $i => $bucketCount) {
            $buckets[$i] -= $bucketCount;
        }

        return new ExplicitBucketHistogramSummary(
            $count,
            $sum,
            $min,
            $max,
            $buckets,
        );
    }

    public function toData(
        array $attributes,
        array $summaries,
        array $exemplars,
        int $startTimestamp,
        int $timestamp,
        Temporality $temporality
    ): Histogram {
        $dataPoints = [];
        foreach ($attributes as $key => $dataPointAttributes) {
            $dataPoints[] = new HistogramDataPoint(
                $summaries[$key]->count,
                $summaries[$key]->sum,
                $summaries[$key]->min,
                $summaries[$key]->max,
                $summaries[$key]->buckets,
                $this->boundaries,
                $dataPointAttributes,
                $startTimestamp,
                $timestamp,
                $exemplars[$key] ?? [],
            );
        }

        return new Histogram(
            $dataPoints,
            $temporality,
        );
    }

    private static function min(float|int $left, float|int $right): float|int {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        return $left <= $right ? $left : ($right <= $left ? $right : NAN);
    }

    private static function max(float|int $left, float|int $right): float|int {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        return $left >= $right ? $left : ($right >= $left ? $right : NAN);
    }
}
