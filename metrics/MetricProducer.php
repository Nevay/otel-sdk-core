<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Amp\Cancellation;
use Nevay\OtelSDK\Metrics\Data\Metric;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricproducer
 */
interface MetricProducer {

    /**
     * @return iterable<Metric>
     *
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#produce-batch
     */
    public function produce(?Cancellation $cancellation = null): iterable;
}
