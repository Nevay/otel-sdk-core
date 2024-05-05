<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Aggregation;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\Histogram;
use Nevay\OTelSDK\Metrics\Data\HistogramDataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;
use function array_fill;
use function count;
use const INF;
use const NAN;

/**
 * @implements Aggregator<ExplicitBucketHistogramSummary, Histogram, HistogramDataPoint>
 *
 * @internal
 */
final class ExplicitBucketHistogramAggregator implements Aggregator {

    /**
     * @param list<float|int> $boundaries strictly ascending histogram bucket boundaries
     */
    public function __construct(
        public readonly array $boundaries,
        public readonly bool $recordMinMax,
        public readonly bool $recordSum,
    ) {}

    public function initialize(): ExplicitBucketHistogramSummary {
        return new ExplicitBucketHistogramSummary(
            0,
            0,
            0,
            +INF,
            -INF,
            array_fill(0, count($this->boundaries) + 1, 0),
        );
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        for ($i = 0, $n = count($this->boundaries); $i < $n && $this->boundaries[$i] < $value; $i++) {}
        $summary->count++;
        self::calculateCompensatedSum($summary, $value);
        $summary->min = self::min($value, $summary->min);
        $summary->max = self::max($value, $summary->max);
        $summary->buckets[$i]++;
    }

    public function merge(mixed $left, mixed $right): ExplicitBucketHistogramSummary {
        $right = clone $right;
        $right->count += $left->count;
        self::calculateCompensatedSum($right, $left->sum);
        self::calculateCompensatedSum($right, -$left->sumCompensation);
        $right->min = self::min($right->min, $left->min);
        $right->max = self::max($right->max, $left->max);
        foreach ($left->buckets as $i => $bucketCount) {
            $right->buckets[$i] += $bucketCount;
        }

        return $right;
    }

    public function diff(mixed $left, mixed $right): ExplicitBucketHistogramSummary {
        $right = clone $right;
        $right->count -= $left->count;
        self::calculateCompensatedSum($right, -$left->sum);
        self::calculateCompensatedSum($right, $left->sumCompensation);
        $right->min = $right->min <= $left->min ? $right->min : NAN;
        $right->max = $right->max >= $left->max ? $right->max : NAN;
        foreach ($left->buckets as $i => $bucketCount) {
            $right->buckets[$i] -= $bucketCount;
        }

        return $right;
    }

    public function toDataPoint(
        Attributes $attributes,
        mixed $summary,
        iterable $exemplars,
        int $startTimestamp,
        int $timestamp,
    ): DataPoint {
        return new HistogramDataPoint(
            $summary->count,
            $this->recordSum ? $summary->sum : null,
            $this->recordMinMax ? $summary->min : null,
            $this->recordMinMax ? $summary->max : null,
            $summary->buckets,
            $this->boundaries,
            $attributes,
            $startTimestamp,
            $timestamp,
            $exemplars,
        );
    }

    public function toData(
        array $dataPoints,
        Temporality $temporality
    ): Histogram {
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

    private static function calculateCompensatedSum(ExplicitBucketHistogramSummary $summary, float|int $value): void {
        $y = $value - $summary->sumCompensation;
        $t = $summary->sum + $y;
        $summary->sumCompensation = $t - $summary->sum - $y;
        $summary->sum = $t;
    }
}
