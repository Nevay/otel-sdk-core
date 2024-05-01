<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

interface Aggregation {

    /**
     * Creates an aggregation for the given instrument type.
     *
     * @param InstrumentType $instrumentType instrument type to resolve
     *        aggregation for
     * @param array $advisory optional advisory provided for the instrument
     * @return Aggregator|null created aggregator, or null if the given
     *         instrument type is not supported by this aggregation
     */
    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator;
}
