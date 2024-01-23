<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use InvalidArgumentException;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingResult;
use Nevay\OTelSDK\Trace\Span\Kind;
use OpenTelemetry\Context\ContextInterface;
use function sprintf;
use function substr_compare;

final class TraceIdRatioBasedSampler implements Sampler {

    private readonly float $ratio;
    private readonly string $threshold;

    /**
     * @param float $ratio sample ratio, must be between 0 and 1 (inclusive)
     */
    public function __construct(float $ratio) {
        if (!($ratio >= 0 && $ratio <= 1)) {
            throw new InvalidArgumentException(sprintf('Ratio (%s) must be be between 0 and 1 (inclusive)', $ratio));
        }

        $this->ratio = $ratio;
        $this->threshold = substr(pack('J', (int) ($ratio * (1 << 56))), 1);
    }

    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult {
        return $this->ratio === 1. || substr_compare($traceId, $this->threshold, 9, 7) < 0
            ? SamplingDecision::RecordAndSample
            : SamplingDecision::Drop;
    }

    public function __toString(): string {
        return sprintf('TraceIdRatioBased{%F}', $this->ratio);
    }
}
