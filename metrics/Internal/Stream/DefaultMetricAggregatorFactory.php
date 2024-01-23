<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Data\Data;

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
        private readonly ?int $cardinalityLimit,
    ) {}

    public function create(): MetricAggregator {
        return new DefaultMetricAggregator($this->aggregation, $this->attributeProcessor, null, $this->cardinalityLimit);
    }
}
