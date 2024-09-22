<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Attributes;

final class View {

    /** @internal */
    public readonly ?string $name;
    /** @internal */
    public readonly ?string $description;
    /** @internal */
    public readonly ?Closure $attributeKeys;
    /** @internal */
    public readonly ?Aggregation $aggregation;
    /** @internal */
    public readonly ?Closure $exemplarReservoir;
    /** @internal */
    public readonly ?int $cardinalityLimit;

    /**
     * @param string|null $name metric stream name
     * @param string|null $description metric stream description
     * @param list<string>|Closure(string): bool|null $attributeKeys attribute
     *        keys that identify the attributes to keep
     * @param Aggregation|null $aggregation aggregation used to aggregate metric
     *        data
     * @param Closure(Aggregator): ExemplarReservoir|null $exemplarReservoir
     *        factory function for exemplar reservoir
     * @param int<0, max>|null $cardinalityLimit aggregation cardinality limit
     */
    public function __construct(
        ?string $name = null,
        ?string $description = null,
        array|Closure|null $attributeKeys = null,
        ?Aggregation $aggregation = null,
        ?Closure $exemplarReservoir = null,
        ?int $cardinalityLimit = null,
    ) {
        if (!$attributeKeys instanceof Closure) {
            $attributeKeys = Attributes::filterKeys(include: $attributeKeys);
        }

        $this->cardinalityLimit = $cardinalityLimit;
        $this->exemplarReservoir = $exemplarReservoir;
        $this->aggregation = $aggregation;
        $this->attributeKeys = $attributeKeys;
        $this->description = $description;
        $this->name = $name;
    }

    public static function drop(): View {
        static $drop = new View(aggregation: new Aggregation\DropAggregation());
        return $drop;
    }
}
