<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Registry;

use OpenTelemetry\API\Metrics\ObserverInterface;

/**
 * @internal
 */
final class NoopObserver implements ObserverInterface {

    public function observe($amount, iterable $attributes = []): void {
        // no-op
    }
}
