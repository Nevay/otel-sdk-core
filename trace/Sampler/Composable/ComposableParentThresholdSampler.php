<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use function sprintf;

/**
 * @experimental
 */
final class ComposableParentThresholdSampler implements ComposableSampler {

    public function __construct(
        private readonly ComposableSampler $rootSampler,
    ) {}

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
        bool $parentThresholdReliable,
    ): SamplingIntent {
        if (!$params->parent->isValid()) {
            return $this->rootSampler->getSamplingIntent(
                $params,
                $parentThreshold,
                $parentThresholdReliable,
            );
        }

        return new SamplingIntent(
            threshold: $parentThreshold,
            thresholdReliable: $parentThresholdReliable,
        );
    }

    public function __toString(): string {
        return sprintf('ParentThreshold{root=%s}', $this->rootSampler);
    }
}
