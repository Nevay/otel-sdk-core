<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

use Closure;

final class NoopStalenessHandler implements StalenessHandler, ReferenceCounter {

    public function acquire(bool $persistent = false): void {
        // no-op
    }

    public function release(): void {
        // no-op
    }

    public function onStale(Closure $callback): void {
        // no-op
    }
}
