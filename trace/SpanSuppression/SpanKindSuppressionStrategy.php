<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanSuppression;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\Internal\SpanSuppression\SpanKindSuppressor;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;
use Nevay\OTelSDK\Trace\SpanSuppressor;

final class SpanKindSuppressionStrategy implements SpanSuppressionStrategy {

    public function getSuppressor(InstrumentationScope $instrumentationScope): SpanSuppressor {
        static $suppressor = new SpanKindSuppressor();
        return $suppressor;
    }
}
