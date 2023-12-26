<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

/**
 * @template TSummary
 * @implements MetricAggregator<TSummary>
 */
final class DefaultMetricAggregator implements MetricAggregator {

    private Aggregation $aggregation;
    private ?AttributeProcessor $attributeProcessor;
    private ?ExemplarReservoir $exemplarReservoir;

    private array $attributes = [];
    private array $summaries = [];

    /**
     * @template TData
     * @param Aggregation<TSummary, TData> $aggregation
     */
    public function __construct(
        Aggregation $aggregation,
        ?AttributeProcessor $attributeProcessor,
        ?ExemplarReservoir $exemplarReservoir,
    ) {
        $this->aggregation = $aggregation;
        $this->attributeProcessor = $attributeProcessor;
        $this->exemplarReservoir = $exemplarReservoir;
    }

    public function record(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        $filteredAttributes = $this->attributeProcessor?->process($attributes, $context) ?? $attributes;
        $raw = $filteredAttributes->toArray();
        $index = $raw ? serialize($raw) : 0;
        $this->attributes[$index] ??= $filteredAttributes;
        $this->aggregation->record(
            $this->summaries[$index] ??= $this->aggregation->initialize(),
            $value,
            $attributes,
            $context,
            $timestamp,
        );
        $this->exemplarReservoir?->offer($index, $value, $attributes, $context, $timestamp);
    }

    public function collect(int $timestamp): Metric {
        $exemplars = $this->exemplarReservoir?->collect($this->attributes) ?? [];
        $metric = new Metric($this->attributes, $this->summaries, $timestamp, $exemplars);

        $this->attributes = [];
        $this->summaries = [];

        return $metric;
    }
}
