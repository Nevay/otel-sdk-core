<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;

final class ObservableUpDownCounter implements ObservableUpDownCounterInterface, InstrumentHandle {
    use AsynchronousInstrument;
}
