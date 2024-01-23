<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingResult;
use Nevay\OTelSDK\Trace\Span\Kind;
use OpenTelemetry\Context\ContextInterface;

final class AlwaysOffSampler implements Sampler {

    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult {
        return SamplingDecision::Drop;
    }

    public function __toString(): string {
        return 'AlwaysOffSampler';
    }
}
