<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

final class Gauge {

    /**
     * @param iterable<NumberDataPoint> $dataPoints
     */
    public function __construct(
        public readonly iterable $dataPoints,
    ) {}
}
