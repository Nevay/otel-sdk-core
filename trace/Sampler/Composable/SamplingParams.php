<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\Span\Link;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * @experimental
 */
final class SamplingParams {

    /**
     * @param ContextInterface $context parent context
     * @param SpanContextInterface $parent parent span context, equivalent to `Span::fromContext($context)->getContext()`
     * @param string $traceId trace id in binary format
     * @param string $spanName span name
     * @param Kind $spanKind span kind
     * @param Attributes $attributes span attributes
     * @param list<Link> $links span links
     */
    public function __construct(
        public readonly ContextInterface $context,
        public readonly SpanContextInterface $parent,
        public readonly string $traceId,
        public readonly string $spanName,
        public readonly Kind $spanKind,
        public readonly Attributes $attributes,
        public readonly array $links,
    ) {}
}
