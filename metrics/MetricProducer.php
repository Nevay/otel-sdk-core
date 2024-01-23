<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;
use Nevay\OTelSDK\Metrics\Data\Metric;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricproducer
 */
interface MetricProducer {

    /**
     * @return iterable<Metric>
     *
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#produce-batch
     */
    public function produce(?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable;
}
