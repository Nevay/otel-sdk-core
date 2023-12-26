<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

final class View {

    /**
     * @param string|null $name name to use instead of original instrument name
     * @param string|false|null $unit unit to use instead of original instrument
     *        unit, false to ignore instrument unit
     * @param string|false|null $description description to use instead of
     *        original description, false to ignore instrument description
     * @param AttributeProcessor|false|null $attributeProcessor attribute
     *        processor, false to disable attribute preprocessing
     * @param AggregationResolver|false|null $aggregationResolver aggregation
     *        resolver, false to drop the metric
     * @param ExemplarReservoirResolver|false|null $exemplarReservoirResolver
     *        exemplar reservoir resolver, false to disable exemplar sampling
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly string|null|false $unit = null,
        public readonly string|null|false $description = null,
        public readonly AttributeProcessor|false|null $attributeProcessor = null,
        public readonly AggregationResolver|false|null $aggregationResolver = null,
        public readonly ExemplarReservoirResolver|false|null $exemplarReservoirResolver = null,
    ) {}

    public static function drop(): View {
        static $drop = new View(attributeProcessor: false, aggregationResolver: false, exemplarReservoirResolver: false);
        return $drop;
    }
}
