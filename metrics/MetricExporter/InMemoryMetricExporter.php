<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\InMemoryExporter;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregation\DefaultAggregation;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\MetricExporter;
use Nevay\OTelSDK\Metrics\TemporalityResolver;
use Nevay\OTelSDK\Metrics\TemporalityResolvers;

/**
 * @extends InMemoryExporter<Metric>
 */
final class InMemoryMetricExporter extends InMemoryExporter implements MetricExporter {

    public function __construct(
        private readonly TemporalityResolver $temporalityResolver = TemporalityResolvers::LowMemory,
        private readonly Aggregation $aggregation = new DefaultAggregation(),
    ) {}

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return $this->temporalityResolver->resolveTemporality($descriptor);
    }

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation {
        return $this->aggregation;
    }
}
