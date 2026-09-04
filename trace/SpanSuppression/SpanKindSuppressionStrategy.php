<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanSuppression;

use Nevay\OTelSDK\Trace\Internal\SpanSuppression\NoopSuppression;
use Nevay\OTelSDK\Trace\Internal\SpanSuppression\SpanKindSuppression;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;

final class SpanKindSuppressionStrategy implements SpanSuppressionStrategy {

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
