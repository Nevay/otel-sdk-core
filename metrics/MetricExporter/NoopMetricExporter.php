<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\NoopExporter;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregation\DropAggregation;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\MetricExporter;

/**
 * @implements NoopExporter<Metric>
 */
final class NoopMetricExporter extends NoopExporter implements MetricExporter {

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return null;
    }

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation {
        return new DropAggregation();
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return 0;
    }
}
