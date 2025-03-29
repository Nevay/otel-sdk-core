<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

/**
 * @experimental
 */
final class SamplingRule {

    public function __construct(
        public readonly Predicate $predicate,
        public readonly ComposableSampler $sampler,
    ) {}
}
