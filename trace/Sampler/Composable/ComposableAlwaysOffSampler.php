<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;

/**
 * @experimental
 */
final class ComposableAlwaysOffSampler implements ComposableSampler {

    private readonly SamplingIntent $intent;

    public function __construct() {
        $this->intent = new SamplingIntent(null, false);
    }

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
    ): SamplingIntent {
        return $this->intent;
    }

    public function __toString(): string {
        return 'AlwaysOff';
    }
}
