<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\HistogramInterface;

final class Histogram implements HistogramInterface, InstrumentHandle {
    use SynchronousInstrument { write as record; }
}
