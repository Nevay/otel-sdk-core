<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressor;

/**
 * @internal
 */
final class NoopSuppressor implements SpanSuppressor {

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        return NoopSuppression::Instance;
    }
}
