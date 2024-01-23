<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\InstrumentationScope;

/**
 * @experimental
 */
interface MetricFilter {

    public function testMetric(
        InstrumentationScope $instrumentationScope,
        string $name,
        InstrumentType $instrumentType,
        ?string $unit,
    ): MetricFilterResult;

    public function testAttributes(
        InstrumentationScope $instrumentationScope,
        string $name,
        InstrumentType $instrumentType,
        ?string $unit,
        Attributes $attributes,
    ): bool;
}
