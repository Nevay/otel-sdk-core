<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

use Nevay\OTelSDK\Common\Attributes;

final class ExponentialHistogramDataPoint implements DataPoint {

    /**
     * @param iterable<Exemplar> $exemplars
     */
    public function __construct(
        public readonly int $count,
        public readonly float|int|null $sum,
        public readonly float|int|null $min,
        public readonly float|int|null $max,
        public readonly int $zeroCount,
        public readonly int $scale,
        public readonly ?ExponentialHistogramBuckets $positive,
        public readonly ?ExponentialHistogramBuckets $negative,
        public readonly Attributes $attributes,
        public readonly int $startTimestamp,
        public readonly int $timestamp,
        public readonly iterable $exemplars = [],
    ) {}
}
