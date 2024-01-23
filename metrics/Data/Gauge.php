<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

/**
 * @implements Data<NumberDataPoint>
 */
final class Gauge implements Data {

    public function __construct(
        public array $dataPoints,
    ) {}
}
