<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;

/**
 * @experimental
 */
final class ComposableAlwaysOnSampler implements ComposableSampler {

    private readonly SamplingIntent $intent;

    public function __construct() {
        $this->intent = new SamplingIntent(0, true);
    }

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
    ): SamplingIntent {
        return $this->intent;
    }

    public function __toString(): string {
        return 'AlwaysOn';
    }
}
