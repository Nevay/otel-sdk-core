<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

/**
 * @template TSummary
 */
interface MetricAggregatorFactory {

    /**
     * @return MetricAggregator<TSummary>
     */
    public function create(): MetricAggregator;
}
