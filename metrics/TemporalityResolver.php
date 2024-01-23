<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;

interface TemporalityResolver {

    /**
     * Resolves the temporality to use for the given stream descriptor.
     *
     * @param Descriptor $descriptor stream descriptor
     * @return Temporality|null temporality to use, or null to drop the stream
     */
    public function resolveTemporality(Descriptor $descriptor): ?Temporality;
}
