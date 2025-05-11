<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SamplingResult;

final class AlwaysOnSampler implements Sampler {

    public function shouldSample(SamplingParams $params): SamplingResult {
        return SamplingDecision::RecordAndSample;
    }

    public function __toString(): string {
        return 'AlwaysOnSampler';
    }
}
