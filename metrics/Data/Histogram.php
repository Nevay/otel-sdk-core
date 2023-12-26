<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

/**
 * @implements Data<HistogramDataPoint>
 */
final class Histogram implements Data {

    public function __construct(
        public array $dataPoints,
        public readonly Temporality $temporality,
    ) {}
}
