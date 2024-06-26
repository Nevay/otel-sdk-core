<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

use Nevay\OTelSDK\Common\Attributes;

final class NumberDataPoint implements DataPoint {

    /**
     * @param float|int $value
     * @param Attributes $attributes
     * @param int $startTimestamp
     * @param int $timestamp
     * @param iterable<Exemplar> $exemplars
     */
    public function __construct(
        public readonly float|int $value,
        public readonly Attributes $attributes,
        public readonly int $startTimestamp,
        public readonly int $timestamp,
        public readonly iterable $exemplars = [],
    ) {}
}
