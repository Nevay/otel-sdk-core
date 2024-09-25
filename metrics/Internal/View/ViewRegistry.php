<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
interface ViewRegistry {

    /**
     * @return iterable<View>
     */
    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable;
}
