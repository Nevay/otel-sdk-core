<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;

final class View {

    /**
     * @param string|null $name metric stream name
     * @param string|null $description metric stream description
     * @param list<string>|null $attributeKeys attribute keys that identify the
     *        attributes to keep
     * @param Aggregation|null $aggregation aggregation used to aggregate metric
     *        data
     * @param Closure(Aggregator): ExemplarReservoir|null $exemplarReservoir
     *        factory function for exemplar reservoir
     * @param int<0, max>|null $cardinalityLimit aggregation cardinality limit
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?array $attributeKeys = null,
        public readonly ?Aggregation $aggregation = null,
        public readonly ?Closure $exemplarReservoir = null,
        public readonly ?int $cardinalityLimit = null,
    ) {}

    public static function drop(): View {
        static $drop = new View(aggregation: new Aggregation\DropAggregation());
        return $drop;
    }
}
