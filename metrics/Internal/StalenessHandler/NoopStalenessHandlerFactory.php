<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\StalenessHandler;

final class NoopStalenessHandlerFactory implements StalenessHandlerFactory {

    public function create(): StalenessHandler&ReferenceCounter {
        return new NoopStalenessHandler();
    }
}
