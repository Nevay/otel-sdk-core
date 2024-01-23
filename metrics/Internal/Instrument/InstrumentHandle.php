<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;

interface InstrumentHandle {

    public function getHandle(): Instrument;
}
