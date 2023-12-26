<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

interface AggregationResolver {

    /**
     * Resolves the aggregation for the given instrument type.
     *
     * @param InstrumentType $instrumentType instrument type to resolve
     *        aggregation for
     * @param array $advisory optional advisory provided for the instrument
     * @return Aggregation|null resolved aggregation, or null to drop the stream
     */
    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation;
}
