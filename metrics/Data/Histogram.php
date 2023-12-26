<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

final class Histogram {

    /**
     * @param iterable<HistogramDataPoint> $dataPoints
     */
    public function __construct(
        public readonly iterable $dataPoints,
        public readonly Temporality $temporality,
    ) {}
}
