<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use AssertionError;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlwaysOffFilter;

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
     */
    public function __construct(
        private readonly Aggregator $aggregator,
        private readonly AttributeProcessor $attributeProcessor,
        private readonly ?int $cardinalityLimit,
    ) {}

    public function create(): MetricAggregator {
        return new DefaultMetricAggregator($this->aggregator, $this->attributeProcessor, new AlwaysOffFilter(), static fn() => throw new AssertionError(), $this->cardinalityLimit);
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
            $aggregator->cardinalityLimit,
        ]));
    }
}
