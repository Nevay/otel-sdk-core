<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Data\Temporality;

interface TemporalityResolver {

    /**
     * Resolves the temporality to use for the given instrument type.
     *
     * @param InstrumentType $instrumentType stream descriptor
     *        synchronous instruments and `Cumulative` for asynchronous instruments
     * @return Temporality temporality to use
     */
    public function resolveTemporality(InstrumentType $instrumentType): Temporality;
}
