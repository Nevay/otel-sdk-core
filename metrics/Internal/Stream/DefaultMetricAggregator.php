<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use Nevay\OtelSDK\Metrics\ExemplarReservoirFactory;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

/**
 * @template TSummary
 * @implements MetricAggregator<TSummary>
 */
final class DefaultMetricAggregator implements MetricAggregator {

    private readonly Aggregation $aggregation;
    private readonly ?AttributeProcessor $attributeProcessor;
    private readonly ?ExemplarReservoirFactory $exemplarReservoirFactory;
    private readonly ?int $cardinalityLimit;

    /** @var array<Attributes> */
    private array $attributes = [];
    /** @var array<TSummary> */
    private array $summaries = [];
    /** @var array<ExemplarReservoir> */
    private array $exemplarReservoirs = [];

    /**
     * @param Aggregation<TSummary, Data> $aggregation
     */
    public function __construct(
        Aggregation $aggregation,
        ?AttributeProcessor $attributeProcessor,
        ?ExemplarReservoirFactory $exemplarReservoirFactory,
        ?int $cardinalityLimit,
    ) {
        $this->aggregation = $aggregation;
        $this->attributeProcessor = $attributeProcessor;
        $this->exemplarReservoirFactory = $exemplarReservoirFactory;
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
        if ($this->exemplarReservoirFactory) {
            $this->exemplarReservoirs[$index] ??= $this->exemplarReservoirFactory->createExemplarReservoir();
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
