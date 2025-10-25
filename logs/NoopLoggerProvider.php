<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Amp\Cancellation;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\NoopLogger;

final class NoopLoggerProvider implements LoggerProviderInterface {

    public function getLogger(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): LoggerInterface {
        return new NoopLogger();
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
