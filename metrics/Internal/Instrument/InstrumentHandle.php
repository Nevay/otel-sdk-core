<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;

/**
 * @internal
 */
interface InstrumentHandle {

    public function getHandle(): Instrument;
}
