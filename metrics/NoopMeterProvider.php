<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeter;

final class NoopMeterProvider implements MeterProviderInterface {

    public function getMeter(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): MeterInterface {
        return new NoopMeter();
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
