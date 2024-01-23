<?php
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\Span\Link;
use OpenTelemetry\Context\ContextInterface;

/**
 * Decides whether a `Span` should be recorded and sampled.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#sampler
 */
interface Sampler {

    /**
     * Returns the sampling decision for a `Span` to be created.
     *
     * @param ContextInterface $context parent context
     * @param string $traceId trace id in binary format
     * @param string $spanName span name
     * @param Kind $spanKind span kind
     * @param Attributes $attributes span attributes
     * @param list<Link> $links span links
     * @return SamplingResult sampling result
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#shouldsample
     */
    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult;

    /**
     * Returns the sampler name or short description with the configuration.
     *
     * @return string sampler name or short description
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#getdescription
     */
    public function __toString(): string;
}
