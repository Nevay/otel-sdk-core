<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Data\Descriptor;
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
     * Resolves the temporality to use for the given stream descriptor.
     *
     * @param Descriptor $descriptor stream descriptor
     * @return Temporality|null temporality to use, or null to drop the stream
     */
    public function resolveTemporality(Descriptor $descriptor): ?Temporality;
}
