<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\HistogramInterface;

/**
 * @internal
 */
final class Histogram implements HistogramInterface, InstrumentHandle {
    use SynchronousInstrument { write as record; }
}
