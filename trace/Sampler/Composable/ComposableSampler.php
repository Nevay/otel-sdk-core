<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;

/**
 * @experimental
 */
interface ComposableSampler {

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
    ): SamplingIntent;

    /**
     * Returns the sampler name or short description with the configuration.
     *
     * @return string sampler name or short description
     */
    public function __toString(): string;
}
