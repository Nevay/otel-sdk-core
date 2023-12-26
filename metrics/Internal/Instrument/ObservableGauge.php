<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableGaugeInterface;

final class ObservableGauge implements ObservableGaugeInterface {
    use AsynchronousInstrument;
}
