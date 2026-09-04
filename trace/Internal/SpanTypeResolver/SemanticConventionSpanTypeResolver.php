<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanTypeResolver;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanTypeResolver;
use function array_key_exists;
use function array_key_first;
use function count;

/**
 * @internal
 */
final class SemanticConventionSpanTypeResolver implements SpanTypeResolver {

    public function __construct(
        private readonly SemanticConventionSpanTypeEntry $internal,
        private readonly SemanticConventionSpanTypeEntry $client,
        private readonly SemanticConventionSpanTypeEntry $server,
        private readonly SemanticConventionSpanTypeEntry $producer,
        private readonly SemanticConventionSpanTypeEntry $consumer,
    ) {}

    public function resolveSpanType(string $spanName, Kind $spanKind, Attributes $attributes): ?string {
        $attributes = $attributes->toArray();

        $entry = match ($spanKind) {
            Kind::Internal => $this->internal,
            Kind::Client => $this->client,
            Kind::Server => $this->server,
            Kind::Producer => $this->producer,
            Kind::Consumer => $this->consumer,
        };
        $candidates = $entry->mask;
        foreach ($entry->attributes as $i => $attribute) {
            $candidates &= $entry->masks[$i << 1 | array_key_exists($attribute, $attributes)];
        }

        if ($candidates == 0) {
            return null;
        }

        $semanticConventions = [];
        for ($i = 0; $candidates; $i++, $candidates >>= 1) {
            if (($candidates & 1) != 0) {
                $semanticConventions[$entry->semanticConventions[$i]] = true;
            }
        }

        if (count($semanticConventions) !== 1) {
            return null;
        }

        return array_key_first($semanticConventions);
    }
}
