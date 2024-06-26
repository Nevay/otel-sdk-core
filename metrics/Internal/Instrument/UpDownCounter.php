<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\UpDownCounterInterface;

/**
 * @internal
 */
final class UpDownCounter implements UpDownCounterInterface, InstrumentHandle {
    use SynchronousInstrument { write as add; }
}
