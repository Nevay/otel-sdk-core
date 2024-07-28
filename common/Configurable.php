<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Closure;

/**
 * @template TConfig
 * 
 * @experimental
 */
interface Configurable {

    /**
     * @param Configurator|Closure(TConfig, InstrumentationScope): void $configurator new
     *        configurator to use
     */
    public function updateConfigurator(Configurator|Closure $configurator): void;
}
