<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

/**
 * @internal
 */
final class NoopStalenessHandlerFactory implements StalenessHandlerFactory {

    public function create(): StalenessHandler&ReferenceCounter {
        return new NoopStalenessHandler();
    }
}
