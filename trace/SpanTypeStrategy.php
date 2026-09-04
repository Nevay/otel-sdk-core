<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\InstrumentationScope;

/**
 * @experimental
 */
interface SpanTypeStrategy {

    public function getResolver(InstrumentationScope $instrumentationScope): SpanTypeResolver;
}
