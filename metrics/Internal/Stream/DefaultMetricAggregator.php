<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use OpenTelemetry\Context\ContextInterface;
use function hash;
use function serialize;

/**
 * @template TSummary
 * @implements MetricAggregator<TSummary>
 *
 * @internal
 */
final class DefaultMetricAggregator implements MetricAggregator {

    private readonly Aggregator $aggregator;
    private readonly ?AttributeProcessor $attributeProcessor;
    private readonly ExemplarFilter $exemplarFilter;
    private readonly Closure $exemplarReservoir;
    private readonly ?int $cardinalityLimit;

    /** @var array<MetricPoint> */
    private array $metricPoints = [];
    /** @var array<ExemplarReservoir> */
    private array $exemplarReservoirs = [];

    /**
     * @param Aggregator<TSummary, Data, DataPoint> $aggregator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        Aggregator $aggregator,
        AttributeProcessor $attributeProcessor,
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
        $processedAttributes = $this->attributeProcessor->process($attributes, $context);
        $index = match ($processedAttributes->count()) {
            default => hash('xxh128', serialize($processedAttributes->toArray()), true),
            '' => 0,
        };
        if (!$metricPoint = $this->metricPoints[$index] ?? null) {
            if (Overflow::check($this->metricPoints, $index, $this->cardinalityLimit)) {
                $index = Overflow::INDEX;
                $this->metricPoints[$index] ??= new MetricPoint(
                    Overflow::attributes(),
                    $this->aggregator->initialize(),
                );
            }
            $metricPoint = $this->metricPoints[$index] ??= new MetricPoint(
                $processedAttributes,
                $this->aggregator->initialize(),
            );
        }

        $this->aggregator->record($metricPoint->summary, $value, $attributes, $context, $timestamp);
        if ($this->exemplarFilter->accepts($value, $attributes, $context, $timestamp)) {
            $this->exemplarReservoirs[$index] ??= ($this->exemplarReservoir)($this->aggregator);
            $this->exemplarReservoirs[$index]->offer($value, $attributes, $context, $timestamp);
        }
    }

    public function collect(int $timestamp): Metric {
        foreach ($this->exemplarReservoirs as $index => $exemplarReservoir) {
            $this->metricPoints[$index]->exemplars = $exemplarReservoir->collect($this->metricPoints[$index]->attributes);
        }
        $metric = new Metric($this->metricPoints, $timestamp);
        $this->metricPoints = [];
        $this->exemplarReservoirs = [];

        return $metric;
    }
}
