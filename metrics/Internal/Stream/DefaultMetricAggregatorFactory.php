<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Closure;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;

/**
 * @template TSummary
 * @implements MetricAggregatorFactory<TSummary>
 *
 * @internal
 */
final class DefaultMetricAggregatorFactory implements MetricAggregatorFactory {

    private readonly string $hash;

    /**
     * @param Aggregator<TSummary, Data, DataPoint> $aggregator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        private readonly Aggregator $aggregator,
        private readonly AttributeProcessor $attributeProcessor,
        private readonly ExemplarFilter $exemplarFilter,
        private readonly Closure $exemplarReservoir,
        private readonly ?int $cardinalityLimit,
    ) {}

    public function create(): MetricAggregator {
        return new DefaultMetricAggregator($this->aggregator, $this->attributeProcessor, $this->exemplarFilter, $this->exemplarReservoir, $this->cardinalityLimit);
    }

    public function equals(MetricAggregatorFactory $other): bool {
        if (!$other instanceof self) {
            return false;
        }

        $this->hash ??= self::computeHash($this);
        $other->hash ??= self::computeHash($other);

        return $this->hash === $other->hash;
    }

    private static function computeHash(self $aggregator): string {
        return hash('xxh128', \Opis\Closure\serialize([
            $aggregator->aggregator,
            $aggregator->attributeProcessor,
            $aggregator->exemplarFilter,
            ($aggregator->exemplarReservoir)($aggregator->aggregator),
            $aggregator->cardinalityLimit,
        ]));
    }
}
