<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * @template TConfig
 *
 * @experimental
 */
interface Configurator {

    /**
     * @param TConfig $config configuration to update
     */
    public function update(mixed $config, InstrumentationScope $instrumentationScope): void;
}
