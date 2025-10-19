<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class DefaultViewRegistry implements ViewRegistry {

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable {
        return [new View()];
    }
}
