<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Instrument;

interface InstrumentHandle {

    public function getHandle(): Instrument;
}
