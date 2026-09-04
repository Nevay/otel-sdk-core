<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanSuppression;

use Nevay\OTelSDK\Trace\Internal\SpanSuppression\SemanticConventionSuppression;
use Nevay\OTelSDK\Trace\Internal\SpanSuppression\SpanKindSuppression;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;

final class SemanticConventionSuppressionStrategy implements SpanSuppressionStrategy {

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        $suppression = match ($params->spanKind) {
            Kind::Internal => SpanKindSuppression::Internal,
            Kind::Client => SpanKindSuppression::Client,
            Kind::Server => SpanKindSuppression::Server,
            Kind::Producer => SpanKindSuppression::Producer,
            Kind::Consumer => SpanKindSuppression::Consumer,
        };

        if ($params->spanType === null) {
            return $suppression;
        }

        return new SemanticConventionSuppression($suppression, [$params->spanType]);
    }
}
