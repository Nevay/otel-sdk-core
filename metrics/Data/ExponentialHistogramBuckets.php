<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

final class ExponentialHistogramBuckets {

    /**
     * @param array<int, int> $buckets
     */
    public function __construct(
        public readonly int $lo,
        public readonly int $hi,
        public readonly array $buckets,
    ) {}
}
