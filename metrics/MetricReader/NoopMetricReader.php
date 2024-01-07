<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\MetricReader;

use Amp\Cancellation;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use Nevay\OtelSDK\Metrics\InstrumentType;
use Nevay\OtelSDK\Metrics\MetricProducer;
use Nevay\OtelSDK\Metrics\MetricReader;

final class NoopMetricReader implements MetricReader {

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

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return null;
    }

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return null;
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return 0;
    }
}
