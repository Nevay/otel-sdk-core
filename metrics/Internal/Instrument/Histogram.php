<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\HistogramInterface;

final class Histogram implements HistogramInterface {
    use SynchronousInstrument { write as record; }
}
