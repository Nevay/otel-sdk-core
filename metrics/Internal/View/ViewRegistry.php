<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\View;

use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\View;

interface ViewRegistry {

    /**
     * @return iterable<View>|null
     */
    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): ?iterable;
}
