<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Trace\SamplingDecision;
use OpenTelemetry\API\Trace\TraceStateInterface;

/**
 * @internal
 */
final class SamplingResult implements \Nevay\OTelSDK\Trace\SamplingResult {

    public function __construct(
        private readonly SamplingDecision $decision,
        private readonly ?TraceStateInterface $traceState = null,
        private readonly iterable $additionalAttributes = [],
    ) {}

    public function shouldRecord(): bool {
        return $this->decision->shouldRecord();
    }

    public function traceFlags(): int {
        return $this->decision->traceFlags();
    }

    public function traceState(): ?TraceStateInterface {
        return $this->traceState;
    }

    public function additionalAttributes(): iterable {
        return $this->additionalAttributes;
    }
}
