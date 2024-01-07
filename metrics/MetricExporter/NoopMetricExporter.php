<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\MetricExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use Nevay\OtelSDK\Metrics\InstrumentType;
use Nevay\OtelSDK\Metrics\MetricExporter;

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

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return null;
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return 0;
    }
}
