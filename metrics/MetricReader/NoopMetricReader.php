<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricReader;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregation\DropAggregation;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Nevay\OTelSDK\Metrics\MetricReader;

final class NoopMetricReader implements MetricReader {

    public function updateResource(Resource $resource): void {
        // no-op
    }

    public function registerProducer(MetricProducer $metricProducer): void {
        // no-op
    }

    public function collect(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function resolveTemporality(InstrumentType $instrumentType): Temporality {
        return Temporality::Cumulative;
    }

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation {
        return new DropAggregation();
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return 0;
    }
}
