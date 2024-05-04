<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

/**
 * @internal
 */
final class Base2ExponentialBucketHistogramSummary {

    public function __construct(
        public int $count,
        public float|int $sum,
        public float|int $sumCompensation,
        public float|int $min,
        public float|int $max,
        public int $zeroCount,
        public Base2ExponentialBucketHistogramBuckets $positive,
        public Base2ExponentialBucketHistogramBuckets $negative,
    ) {}
}
