<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressor;

/**
 * @internal
 */
final class SpanKindSuppressor implements SpanSuppressor {

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        return match ($params->spanKind) {
            Kind::Internal => NoopSuppression::Instance,
            Kind::Client => SpanKindSuppression::Client,
            Kind::Server => SpanKindSuppression::Server,
            Kind::Producer => SpanKindSuppression::Producer,
            Kind::Consumer => SpanKindSuppression::Consumer,
        };
    }
}
