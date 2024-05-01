<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregation\DropAggregation;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\MetricExporter;

final class NoopMetricExporter implements MetricExporter {

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        return Future::complete(true);
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

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation {
        return new DropAggregation();
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return 0;
    }
}
