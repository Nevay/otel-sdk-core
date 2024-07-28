<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Closure;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;

/**
 * Configurator that applies a callback unconditionally.
 *
 * @template TConfig
 * @implements Configurator<TConfig>
 *
 * @internal
 */
final class CallbackConfigurator implements Configurator {

    /**
     * @param Closure(TConfig, InstrumentationScope): void $configurator
     */
    public function __construct(
        private readonly Closure $configurator,
    ) {}

    public function update(mixed $config, InstrumentationScope $instrumentationScope): bool {
        ($this->configurator)($config, $instrumentationScope);

        return true;
    }

    public function appliesTo(InstrumentationScope $instrumentationScope): bool {
        return true;
    }
}
