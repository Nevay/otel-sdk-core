<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanSuppression;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\Internal\SpanSuppression\NoopSuppressor;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;
use Nevay\OTelSDK\Trace\SpanSuppressor;

final class NoopSuppressionStrategy implements SpanSuppressionStrategy {

    public function getSuppressor(InstrumentationScope $instrumentationScope): SpanSuppressor {
        static $suppressor = new NoopSuppressor();
        return $suppressor;
    }
}
