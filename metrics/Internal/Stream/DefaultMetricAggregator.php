<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

/**
 * @template TSummary
 * @implements MetricAggregator<TSummary>
 */
final class DefaultMetricAggregator implements MetricAggregator {

    private readonly Aggregation $aggregation;
    private readonly ?AttributeProcessor $attributeProcessor;
    private readonly ?ExemplarReservoir $exemplarReservoir;
    private readonly ?int $cardinalityLimit;

    private array $attributes = [];
    private array $summaries = [];

    /**
     * @param Aggregation<TSummary, Data> $aggregation
     */
    public function __construct(
        Aggregation $aggregation,
        ?AttributeProcessor $attributeProcessor,
        ?ExemplarReservoir $exemplarReservoir,
        ?int $cardinalityLimit,
    ) {
        $this->aggregation = $aggregation;
        $this->attributeProcessor = $attributeProcessor;
        $this->exemplarReservoir = $exemplarReservoir;
        $this->cardinalityLimit = $cardinalityLimit;
    }

    public function record(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        $filteredAttributes = $this->attributeProcessor?->process($attributes, $context) ?? $attributes;
        $raw = $filteredAttributes->toArray();
        $index = $raw ? serialize($raw) : 0;
        if (Overflow::check($this->attributes, $index, $this->cardinalityLimit)) {
            $index = Overflow::INDEX;
            $filteredAttributes = Overflow::attributes();
        }
        $this->attributes[$index] ??= $filteredAttributes;
        $this->aggregation->record(
            $this->summaries[$index] ??= $this->aggregation->initialize(),
            $value,
            $attributes,
            $context,
            $timestamp,
        );
        if ($index !== Overflow::INDEX) {
            $this->exemplarReservoir?->offer($index, $value, $attributes, $context, $timestamp);
        }
    }

    public function collect(int $timestamp): Metric {
        $exemplars = $this->exemplarReservoir?->collect($this->attributes) ?? [];
        $metric = new Metric($this->attributes, $this->summaries, $timestamp, $exemplars);

        $this->attributes = [];
        $this->summaries = [];

        return $metric;
    }
}
