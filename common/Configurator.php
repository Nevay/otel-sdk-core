<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * @template TConfig
 *
 * @experimental
 */
interface Configurator {

    /**
     * @param mixed $config configuration to update
     * @return bool whether this configurator modified the given configuration
     */
    public function update(mixed $config, InstrumentationScope $instrumentationScope): bool;

    /**
     * @return bool whether this configurator applies to the given instrumentation scope
     */
    public function appliesTo(InstrumentationScope $instrumentationScope): bool;
}
