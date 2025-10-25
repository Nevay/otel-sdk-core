<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;

/**
 * A configurator that applies no changes.
 *
 * @template TConfig
 * @implements Configurator<TConfig>
 *
 * @internal
 */
final class NoopConfigurator implements Configurator {

    public function update(mixed $config, InstrumentationScope $instrumentationScope): void {
        // no-op
    }
}
