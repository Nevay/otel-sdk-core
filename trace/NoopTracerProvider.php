<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\Configurator;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\TracerInterface;

final class NoopTracerProvider implements TracerProviderInterface {

    public function getTracer(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): TracerInterface {
        return new NoopTracer();
    }

    public function updateConfigurator(Configurator|Closure $configurator): void {
        // no-op
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
