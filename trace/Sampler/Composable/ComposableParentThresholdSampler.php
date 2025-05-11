<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;
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
    ): SamplingIntent {
        if ($parentThreshold !== null) {
            return new SamplingIntent($parentThreshold, true);
        }

        if (!$params->parent->isValid()) {
            return $this->rootSampler->getSamplingIntent(
                $params,
                $parentThreshold,
            );
        }

        return $params->parent->isSampled()
            ? new SamplingIntent(0, false)
            : new SamplingIntent(null, false);
    }

    public function __toString(): string {
        return sprintf('ParentThreshold{root=%s}', $this->rootSampler);
    }
}
