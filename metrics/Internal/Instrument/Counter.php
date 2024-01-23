<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\CounterInterface;

final class Counter implements CounterInterface, InstrumentHandle {
    use SynchronousInstrument { write as add; }
}
