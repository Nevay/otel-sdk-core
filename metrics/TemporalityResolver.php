<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\Internal\TemporalityResolvers;

interface TemporalityResolver {

    /**
     * @see Temporality::Delta
     */
    const Delta = TemporalityResolvers::DeltaResolver;
    /**
     * @see Temporality::Cumulative
     */
    const Cumulative = TemporalityResolvers::CumulativeResolver;
    /**
     * Low Memory mode, uses the preferred temporality of the underlying metric data stream.
     */
    const LowMemory = TemporalityResolvers::LowMemoryResolver;

    /**
     * Resolves the temporality to use for the given instrument type.
     *
     * @param InstrumentType $instrumentType stream descriptor
     * @param Temporality $preferredTemporality preferred temporality of the underlying metric stream, `Delta` for
     *        synchronous instruments and `Cumulative` for asynchronous instruments
     * @return Temporality temporality to use
     */
    public function resolveTemporality(InstrumentType $instrumentType, Temporality $preferredTemporality): Temporality;
}
