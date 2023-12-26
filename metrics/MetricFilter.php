<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Common\InstrumentationScope;

/**
 * @experimental
 */
interface MetricFilter {

    public function testMetric(
        InstrumentationScope $instrumentationScope,
        string $name,
        Aggregation $aggregation,
        ?string $unit,
    ): MetricFilterResult;

    public function testAttributes(
        InstrumentationScope $instrumentationScope,
        string $name,
        Aggregation $aggregation,
        ?string $unit,
        Attributes $attributes,
    ): bool;
}
