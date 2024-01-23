<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Metrics\Data\Metric;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricexporter
 */
interface MetricExporter extends TemporalityResolver, AggregationResolver, CardinalityLimitResolver {

    /**
     * @param iterable<Metric> $batch
     * @return Future<bool>
     *
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#exportbatch
     */
    public function export(iterable $batch, ?Cancellation $cancellation = null): Future;

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#shutdown-2
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#forceflush-2
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
