<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\GaugeInterface;

/**
 * @experimental
 */
final class Gauge implements GaugeInterface, InstrumentHandle {
    use SynchronousInstrument { write as record; }
}
