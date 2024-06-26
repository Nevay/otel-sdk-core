<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableCounterInterface;

/**
 * @internal
 */
final class ObservableCounter implements ObservableCounterInterface, InstrumentHandle {
    use AsynchronousInstrument;
}
