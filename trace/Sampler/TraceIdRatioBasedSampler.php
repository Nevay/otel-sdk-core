<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Sampler;

use InvalidArgumentException;
use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Trace\Sampler;
use Nevay\OtelSDK\Trace\SamplingDecision;
use Nevay\OtelSDK\Trace\SamplingResult;
use Nevay\OtelSDK\Trace\Span\Kind;
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

    public function getDescription(): string {
        return sprintf('TraceIdRatioBased{%F}', $this->ratio);
    }
}
