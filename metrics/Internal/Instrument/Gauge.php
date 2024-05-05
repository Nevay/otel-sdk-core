<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\GaugeInterface;

/**
 * @internal
 */
final class Gauge implements GaugeInterface, InstrumentHandle {
    use SynchronousInstrument { write as record; }
}
