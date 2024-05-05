<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

/**
 * @internal
 */
final class DelayedStalenessHandlerFactory implements StalenessHandlerFactory {

    public function __construct(
        private readonly float $delay,
    ) {}

    public function create(): StalenessHandler&ReferenceCounter {
        return new DelayedStalenessHandler($this->delay);
    }
}
