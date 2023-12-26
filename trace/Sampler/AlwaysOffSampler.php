<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Sampler;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Trace\Sampler;
use Nevay\OtelSDK\Trace\SamplingDecision;
use Nevay\OtelSDK\Trace\SamplingResult;
use Nevay\OtelSDK\Trace\Span\Kind;
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

    public function getDescription(): string {
        return 'AlwaysOffSampler';
    }
}
