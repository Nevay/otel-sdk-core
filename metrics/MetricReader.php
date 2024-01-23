<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricreader
 */
interface MetricReader extends TemporalityResolver, AggregationResolver, CardinalityLimitResolver {

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricproducer
     */
    public function registerProducer(MetricProducer $metricProducer): void;

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#collect
     */
    public function collect(?Cancellation $cancellation = null): bool;

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#shutdown-1
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#forceflush-1
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
