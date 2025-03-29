<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

/**
 * @experimental
 */
final class ComposableParentThresholdSampler implements ComposableSampler {

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
        bool $parentThresholdReliable,
    ): SamplingIntent {
        return new SamplingIntent(
            threshold: $parentThreshold,
            thresholdReliable: $parentThresholdReliable,
        );
    }

    public function __toString(): string {
        return 'ParentThreshold';
    }
}
