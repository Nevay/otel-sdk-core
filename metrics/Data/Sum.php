<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

/**
 * @implements Data<NumberDataPoint>
 */
final class Sum implements Data {

    public function __construct(
        public array $dataPoints,
        public readonly Temporality $temporality,
        public readonly bool $monotonic,
    ) {}
}
