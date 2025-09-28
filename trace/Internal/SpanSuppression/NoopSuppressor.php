<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressor;

/**
 * @internal
 */
final class NoopSuppressor implements SpanSuppressor {

    public function resolveSuppression(Kind $spanKind, Attributes $attributes): SpanSuppression {
        return NoopSuppression::Instance;
    }
}
