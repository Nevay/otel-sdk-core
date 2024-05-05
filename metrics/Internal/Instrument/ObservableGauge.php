<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableGaugeInterface;

/**
 * @internal
 */
final class ObservableGauge implements ObservableGaugeInterface, InstrumentHandle {
    use AsynchronousInstrument;
}
