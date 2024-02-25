<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;
use Countable;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Traversable;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#metricproducer
 */
interface MetricProducer {

    /**
     * Calling this method SHOULD NOT block, any expensive operations SHOULD be
     * executed on traversal of the returned iterable.
     *
     * @return list<Metric>|(Traversable<Metric>&Countable)
     *
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#produce-batch
     */
    public function produce(?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable;
}
