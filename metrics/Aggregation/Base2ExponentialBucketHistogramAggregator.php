<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\ExponentialHistogram;
use Nevay\OTelSDK\Metrics\Data\ExponentialHistogramDataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;
use function abs;
use function ceil;
use function is_finite;
use function log;
use function min;
use function pack;
use function unpack;
use const INF;
use const M_LOG2E;
use const NAN;

/**
 * @implements Aggregator<Base2ExponentialBucketHistogramSummary, ExponentialHistogram, ExponentialHistogramDataPoint>
 *
 * @internal
 */
final class Base2ExponentialBucketHistogramAggregator implements Aggregator {

    public function __construct(
        public readonly int $maxSize,
        public readonly int $maxScale,
        public readonly bool $recordMinMax,
        public readonly bool $recordSum,
    ) {}

    public function initialize(): Base2ExponentialBucketHistogramSummary {
        return new Base2ExponentialBucketHistogramSummary(
            0,
            0,
            0,
            +INF,
            -INF,
            0,
            new Base2ExponentialBucketHistogramBuckets($this->maxScale),
            new Base2ExponentialBucketHistogramBuckets($this->maxScale),
        );
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        if (!is_finite($value)) {
            return;
        }

        $summary->count++;
        self::calculateCompensatedSum($summary, $value);
        $summary->min = self::min($value, $summary->min);
        $summary->max = self::max($value, $summary->max);

        if ($value == 0) {
            $summary->zeroCount++;
            return;
        }

        $buckets = $value > 0
            ? $summary->positive
            : $summary->negative;

        $buckets->increment(self::mapToIndex(abs($value), $buckets->scale), $this->maxSize);
    }

    public function merge(mixed $left, mixed $right): mixed {
        $right = clone $right;
        $right->count += $left->count;
        self::calculateCompensatedSum($right, $left->sum);
        self::calculateCompensatedSum($right, -$left->sumCompensation);
        $right->min = self::min($right->min, $left->min);
        $right->max = self::max($right->max, $left->max);
        $right->zeroCount += $left->zeroCount;
        $right->positive = $right->positive->merge($left->positive, $this->maxSize, 1);
        $right->negative = $right->negative->merge($left->negative, $this->maxSize, 1);

        return $right;
    }

    public function diff(mixed $left, mixed $right): mixed {
        $right = clone $right;
        $right->count -= $left->count;
        self::calculateCompensatedSum($right, -$left->sum);
        self::calculateCompensatedSum($right, $left->sumCompensation);
        $right->min = $right->min <= $left->min ? $right->min : NAN;
        $right->max = $right->max >= $left->max ? $right->max : NAN;
        $right->zeroCount -= $left->zeroCount;
        $right->positive = $right->positive->merge($left->positive, $this->maxSize, -1);
        $right->negative = $right->negative->merge($left->negative, $this->maxSize, -1);

        return $right;
    }

    public function toDataPoint(
        Attributes $attributes,
        mixed $summary,
        iterable $exemplars,
        int $startTimestamp,
        int $timestamp,
    ): DataPoint {
        $scale = min($summary->positive->scale, $summary->negative->scale);

        return new ExponentialHistogramDataPoint(
            $summary->count,
            $this->recordSum ? $summary->sum : null,
            $this->recordMinMax ? $summary->min : null,
            $this->recordMinMax ? $summary->max : null,
            $summary->zeroCount,
            $scale,
            $summary->positive->toData($scale),
            $summary->negative->toData($scale),
            $attributes,
            $startTimestamp,
            $timestamp,
            $exemplars,
        );
    }

    public function toData(
        array $dataPoints,
        Temporality $temporality,
    ): Data {
        return new ExponentialHistogram(
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

    private static function calculateCompensatedSum(Base2ExponentialBucketHistogramSummary $summary, float|int $value): void {
        $y = $value - $summary->sumCompensation;
        $t = $summary->sum + $y;
        $summary->sumCompensation = $t - $summary->sum - $y;
        $summary->sum = $t;
    }

    private static function mapToIndex(float $value, int $scale): int {
        if ($scale > 0) {
            return (int) ceil(log($value) * M_LOG2E * 2 ** $scale) - 1;
        }

        $b = unpack('J', pack('E', $value))[1];
        $e = $b >> 52 & (1 << 11) - 1;
        $f = $b & (1 << 52) - 1;

        return $e - 1023 - !$f >> -$scale;
    }
}
