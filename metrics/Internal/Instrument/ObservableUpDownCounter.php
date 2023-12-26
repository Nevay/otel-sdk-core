<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;

final class ObservableUpDownCounter implements ObservableUpDownCounterInterface {
    use AsynchronousInstrument;
}
