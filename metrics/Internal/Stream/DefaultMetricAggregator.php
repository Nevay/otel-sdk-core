<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

/**
 * @template TSummary
 * @implements MetricAggregator<TSummary>
 */
final class DefaultMetricAggregator implements MetricAggregator {

    private readonly Aggregator $aggregator;
    private readonly ?AttributeProcessor $attributeProcessor;
    private readonly ExemplarFilter $exemplarFilter;
    private readonly Closure $exemplarReservoir;
    private readonly ?int $cardinalityLimit;

    /** @var array<Attributes> */
    private array $attributes = [];
    /** @var array<TSummary> */
    private array $summaries = [];
    /** @var array<ExemplarReservoir> */
    private array $exemplarReservoirs = [];

    /**
     * @param Aggregator<TSummary, Data> $aggregator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        Aggregator $aggregator,
        ?AttributeProcessor $attributeProcessor,
        ExemplarFilter $exemplarFilter,
        Closure $exemplarReservoir,
        ?int $cardinalityLimit,
    ) {
        $this->aggregator = $aggregator;
        $this->attributeProcessor = $attributeProcessor;
        $this->exemplarFilter = $exemplarFilter;
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
        $this->aggregator->record(
            $this->summaries[$index] ??= $this->aggregator->initialize(),
            $value,
            $attributes,
            $context,
            $timestamp,
        );
        if ($this->exemplarFilter->accepts($value, $attributes, $context, $timestamp)) {
            $this->exemplarReservoirs[$index] ??= ($this->exemplarReservoir)($this->aggregator);
            $this->exemplarReservoirs[$index]->offer($value, $attributes, $context, $timestamp);
        }
    }

    public function collect(int $timestamp): Metric {
        $exemplars = [];
        foreach ($this->exemplarReservoirs as $index => $exemplarReservoir) {
            if ($dataPointExemplars = $exemplarReservoir->collect($this->attributes[$index])) {
                $exemplars[$index] = $dataPointExemplars;
            }
        }
        $metric = new Metric($this->attributes, $this->summaries, $timestamp, $exemplars);

        $this->attributes = [];
        $this->summaries = [];
        $this->exemplarReservoirs = [];

        return $metric;
    }
}
