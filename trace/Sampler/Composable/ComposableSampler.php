<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

/**
 * @experimental
 */
interface ComposableSampler {

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
        bool $parentThresholdReliable,
    ): SamplingIntent;

    /**
     * Returns the sampler name or short description with the configuration.
     *
     * @return string sampler name or short description
     */
    public function __toString(): string;
}
