<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanType;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;
use Nevay\OTelSDK\Trace\Internal\SpanTypeResolver\SemanticConventionSpanTypeEntry;
use Nevay\OTelSDK\Trace\Internal\SpanTypeResolver\SemanticConventionSpanTypeResolver;
use Nevay\OTelSDK\Trace\Span;
use Nevay\OTelSDK\Trace\SpanTypeResolver;
use Nevay\OTelSDK\Trace\SpanTypeStrategy;
use OpenTelemetry\API\Trace\SpanSuppression\SemanticConvention;
use OpenTelemetry\API\Trace\SpanSuppression\SemanticConventionResolver;
use function array_column;
use function array_fill;
use function array_keys;
use function assert;
use function extension_loaded;
use function gmp_init;
use function sprintf;
use function strcspn;
use function strlen;
use function trigger_error;
use const E_USER_WARNING;
use const PHP_INT_MAX;
use const PHP_INT_SIZE;

/**
 * @experimental
 */
final class SemanticConventionSpanTypeStrategy implements SpanTypeStrategy {

    /**
     * @param iterable<SemanticConventionResolver> $resolvers
     */
    public function __construct(
        private readonly iterable $resolvers,
    ) {}

    public function getResolver(InstrumentationScope $instrumentationScope): SpanTypeResolver {
        /** @var array<string, list<SemanticConvention>> $semanticConventionsBySpanKind */
        $semanticConventionsBySpanKind = [];
        foreach ($this->resolvers as $resolver) {
            foreach ($resolver->resolveSemanticConventions($instrumentationScope->name, $instrumentationScope->version, $instrumentationScope->schemaUrl) as $semanticConvention) {
                if (!$semanticConvention->samplingAttributes) {
                    continue;
                }

                $spanKind = Span\Kind::fromApi($semanticConvention->spanKind);
                $semanticConventionsBySpanKind[$spanKind->name][] = $semanticConvention;
            }
        }

        $entries = [];
        foreach ($semanticConventionsBySpanKind as $spanKind => $semanticConventions) {
            $attributes = [];
            foreach ($semanticConventions as $semanticConvention) {
                foreach ($semanticConvention->samplingAttributes as $attribute) {
                    assert(strcspn($attribute, "*?") === strlen($attribute));
                    $attributes[$attribute] ??= count($attributes);
                }
            }

            $n = count($semanticConventions);
            $max = (PHP_INT_SIZE << 3) - 1;
            if ($n < $max) {
                $mask = (1 << $n) - 1;
            } elseif ($n === $max) {
                $mask = PHP_INT_MAX;
            } elseif (!extension_loaded('gmp')) {
                trigger_error(sprintf('GMP extension required to support over %d %s semantic conventions for instrumentation scope name="%s" version="%s" schemaUrl="%s"',
                    $max, $spanKind, $instrumentationScope->name, $instrumentationScope->version, $instrumentationScope->schemaUrl), E_USER_WARNING);
                $mask = PHP_INT_MAX;
            } else {
                $mask = (gmp_init(1) << $n) - 1;
            }
            $masks = array_fill(0, count($attributes) << 1, $mask);
            $one = $mask & 1;

            foreach ($semanticConventions as $i => $semanticConvention) {
                $builder = new WildcardPatternMatcherBuilder();
                foreach ($semanticConvention->samplingAttributes as $attribute) {
                    $builder->add($attribute, null);
                }
                foreach ($semanticConvention->attributes as $attribute) {
                    $builder->add($attribute, null);
                }
                $matcher = $builder->build();

                foreach ($semanticConvention->samplingAttributes as $attribute) {
                    $o = $attributes[$attribute];
                    $masks[$o << 1] ^= $one << $i;
                }
                foreach ($attributes as $attribute => $o) {
                    if (!$matcher->matches($attribute)) {
                        $masks[$o << 1 | 1] ^= $one << $i;
                    }
                }
            }

            $entries[$spanKind] = new SemanticConventionSpanTypeEntry(
                mask: $mask,
                semanticConventions: array_column($semanticConventions, 'name'),
                attributes: array_keys($attributes),
                masks: $masks,
            );
        }

        static $empty = new SemanticConventionSpanTypeEntry(0, [], [], []);

        return new SemanticConventionSpanTypeResolver(
            internal: $entries[Span\Kind::Internal->name] ?? $empty,
            client: $entries[Span\Kind::Client->name] ?? $empty,
            server: $entries[Span\Kind::Server->name] ?? $empty,
            producer: $entries[Span\Kind::Producer->name] ?? $empty,
            consumer: $entries[Span\Kind::Consumer->name] ?? $empty,
        );
    }
}
