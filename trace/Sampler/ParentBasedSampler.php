<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingResult;
use Nevay\OTelSDK\Trace\Span\Kind;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;

final class ParentBasedSampler implements Sampler {

    public function __construct(
        private readonly Sampler $root,
        private readonly Sampler $remoteParentSampled    = new AlwaysOnSampler(),
        private readonly Sampler $remoteParentNotSampled = new AlwaysOffSampler(),
        private readonly Sampler $localParentSampled     = new AlwaysOnSampler(),
        private readonly Sampler $localParentNotSampled  = new AlwaysOffSampler(),
    ) {}

    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult {
        $parent = Span::fromContext($context)->getContext();

        $sampler = match (true) {
            !$parent->isValid() => $this->root,
            $parent->isRemote() && $parent->isSampled() => $this->remoteParentSampled,
            $parent->isRemote() && !$parent->isSampled() => $this->remoteParentNotSampled,
            !$parent->isRemote() && $parent->isSampled() => $this->localParentSampled,
            !$parent->isRemote() && !$parent->isSampled() => $this->localParentNotSampled,
        };

        return $sampler->shouldSample($context, $traceId, $spanName, $spanKind, $attributes, $links);
    }

    public function __toString(): string {
        return sprintf(
            'ParentBased{%s,%s,%s,%s,%s}',
            $this->root,
            $this->remoteParentSampled,
            $this->remoteParentNotSampled,
            $this->localParentSampled,
            $this->localParentNotSampled,
        );
    }
}
