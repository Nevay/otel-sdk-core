<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;

/**
 * @template TSummary
 * @implements MetricAggregatorFactory<TSummary>
 */
final class DefaultMetricAggregatorFactory implements MetricAggregatorFactory {

    /**
     * @template TData
     * @param Aggregation<TSummary, TData> $aggregation
     */
    public function __construct(
        private readonly Aggregation $aggregation,
        private readonly ?AttributeProcessor $attributeProcessor,
    ) {}

    public function create(): MetricAggregator {
        return new DefaultMetricAggregator($this->aggregation, $this->attributeProcessor, null);
    }
}
