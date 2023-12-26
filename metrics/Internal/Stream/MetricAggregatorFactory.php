<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

/**
 * @template TSummary
 */
interface MetricAggregatorFactory {

    /**
     * @return MetricAggregator<TSummary>
     */
    public function create(): MetricAggregator;
}
