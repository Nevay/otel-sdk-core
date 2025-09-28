<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressor;
use function array_key_exists;

/**
 * @internal
 */
final class SemanticConventionSuppressor implements SpanSuppressor {

    public function __construct(
        private readonly SemanticConventionSuppressionEntry $internal,
        private readonly SemanticConventionSuppressionEntry $client,
        private readonly SemanticConventionSuppressionEntry $server,
        private readonly SemanticConventionSuppressionEntry $producer,
        private readonly SemanticConventionSuppressionEntry $consumer,
    ) {}

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        $attributes = $params->attributes->toArray();

        $entry = match ($params->spanKind) {
            Kind::Internal => $this->internal,
            Kind::Client => $this->client,
            Kind::Server => $this->server,
            Kind::Producer => $this->producer,
            Kind::Consumer => $this->consumer,
        };
        $candidates = $filter = $entry->mask;
        foreach ($entry->attributes as $i => $attribute) {
            // If attribute is present: keep semconvs containing this attribute
            // If attribute is not present: keep semconvs not containing this attribute as sampling relevant attribute
            if (array_key_exists($attribute, $attributes)) {
                $filter &= $entry->masks[$i << 1 | 1];
            } else {
                $candidates &= $entry->masks[$i << 1];
            }
        }

        if ($candidates == 0 && $params->spanKind === Kind::Internal) {
            return NoopSuppression::Instance;
        }

        $suppression = match ($params->spanKind) {
            Kind::Internal => SpanKindSuppression::Internal,
            Kind::Client => SpanKindSuppression::Client,
            Kind::Server => SpanKindSuppression::Server,
            Kind::Producer => SpanKindSuppression::Producer,
            Kind::Consumer => SpanKindSuppression::Consumer,
        };

        if ($candidates == 0) {
            return $suppression;
        }

        if (($candidates & $filter) != 0) {
            $candidates &= $filter;
        }

        $semanticConventions = [];
        for ($i = 0; $candidates; $i++, $candidates >>= 1) {
            if (($candidates & 1) != 0) {
                $semanticConventions[] = $entry->semanticConventions[$i];
            }
        }

        return new SemanticConventionSuppression($suppression, $semanticConventions);
    }
}
