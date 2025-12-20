<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Trace\Internal;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SamplingResult;
use function sprintf;

/**
 * @experimental
 */
final class AlwaysRecordSampler implements Sampler {

    public function __construct(
        private readonly Sampler $delegate,
    ) {}

    public function shouldSample(SamplingParams $params): SamplingResult {
        $result = $this->delegate->shouldSample($params);

        if ($result->shouldRecord()) {
            return $result;
        }

        return new Internal\SamplingResult(
            decision: SamplingDecision::RecordOnly,
            traceState: $result->traceState(),
            additionalAttributes: $result->additionalAttributes(),
        );
    }

    public function __toString(): string {
        return sprintf('AlwaysRecordSampler{delegate=%s}', $this->delegate);
    }
}
