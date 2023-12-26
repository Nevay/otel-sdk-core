<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\Data\Data;

/**
 * @template TSummary
 * @implements MetricAggregatorFactory<TSummary>
 */
final class DefaultMetricAggregatorFactory implements MetricAggregatorFactory {

    /**
     * @param Aggregation<TSummary, Data> $aggregation
     */
    public function __construct(
        private readonly Aggregation $aggregation,
        private readonly ?AttributeProcessor $attributeProcessor,
    ) {}

    public function create(): MetricAggregator {
        return new DefaultMetricAggregator($this->aggregation, $this->attributeProcessor, null);
    }
}
