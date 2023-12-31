<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\UpDownCounterInterface;

final class UpDownCounter implements UpDownCounterInterface, InstrumentHandle {
    use SynchronousInstrument { write as add; }
}
