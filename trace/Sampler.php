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
     * @param SamplingParams $params sampling parameters
     * @return SamplingResult sampling result
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#shouldsample
     */
    public function shouldSample(SamplingParams $params): SamplingResult;

    /**
     * Returns the sampler name or short description with the configuration.
     *
     * @return string sampler name or short description
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#getdescription
     */
    public function __toString(): string;
}
