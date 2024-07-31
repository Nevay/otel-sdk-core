<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;

/**
 * @internal
 */
final class RegisteredInstrument {

    public function __construct(
        public readonly Instrument $instrument,
        public readonly InstrumentationScope $instrumentationScope,
        public readonly ReferenceCounter $referenceCounter,
        public bool $dormant,
    ) {}
}
