<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanSuppression;

use Nevay\OTelSDK\Trace\Internal\SpanSuppression\NoopSuppression;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;

final class NoopSuppressionStrategy implements SpanSuppressionStrategy {

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        return NoopSuppression::Instance;
    }
}
