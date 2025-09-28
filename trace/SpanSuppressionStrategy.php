<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\InstrumentationScope;

/**
 * @experimental
 */
interface SpanSuppressionStrategy {

    public function getSuppressor(InstrumentationScope $instrumentationScope): SpanSuppressor;
}
