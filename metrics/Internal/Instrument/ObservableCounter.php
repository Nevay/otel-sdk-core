<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use OpenTelemetry\API\Metrics\ObservableCounterInterface;

final class ObservableCounter implements ObservableCounterInterface {
    use AsynchronousInstrument;
}
