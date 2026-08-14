<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use function is_array;

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
    /** @internal */
    public readonly ?bool $enabled;

    /**
     * @param string|null $name metric stream name
     * @param string|null $description metric stream description
     * @param list<string>|Closure(string): bool|null $attributeKeys attribute
     *        keys that identify the attributes to keep
     * @param Aggregation|null $aggregation aggregation used to aggregate metric
     *        data
     * @param Closure(Aggregator): ExemplarReservoir|null $exemplarReservoir
     *        factory function for exemplar reservoir
     * @param bool|null $enabled whether this view is enabled
     * @param int<0, max>|null $cardinalityLimit aggregation cardinality limit
     */
    public function __construct(
        ?string $name = null,
        ?string $description = null,
        array|Closure|null $attributeKeys = null,
        ?Aggregation $aggregation = null,
        ?Closure $exemplarReservoir = null,
        ?int $cardinalityLimit = null,
        ?bool $enabled = null,
    ) {
        if (is_array($attributeKeys)) {
            $attributeKeys = Attributes::filterKeys(include: $attributeKeys);
        }

        $this->name = $name;
        $this->description = $description;
        $this->attributeKeys = $attributeKeys;
        $this->aggregation = $aggregation;
        $this->exemplarReservoir = $exemplarReservoir;
        $this->cardinalityLimit = $cardinalityLimit;
        $this->enabled = $enabled;
    }

    public static function drop(): View {
        static $drop = new View(aggregation: new Aggregation\DropAggregation());
        return $drop;
    }
}
