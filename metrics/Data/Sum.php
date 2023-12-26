<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

final class Sum {

    /**
     * @param iterable<NumberDataPoint> $dataPoints
     */
    public function __construct(
        public readonly iterable $dataPoints,
        public readonly Temporality $temporality,
        public bool $monotonic,
    ) {}
}
