<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

final class ExplicitBucketHistogramSummary {

    /**
     * @param list<int> $buckets
     */
    public function __construct(
        public int $count,
        public float|int $sum,
        public float|int $sumCompensation,
        public float|int $min,
        public float|int $max,
        public array $buckets,
    ) {}
}
