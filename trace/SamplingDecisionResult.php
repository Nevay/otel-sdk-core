<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace;

use OpenTelemetry\API\Trace\TraceStateInterface;

final class SamplingDecisionResult implements SamplingResult {

    public function __construct(
        private readonly SamplingDecision $samplingDecision,
        private readonly ?TraceStateInterface $traceState = null,
        private readonly iterable $additionalAttributes = [],
    ) {}

    public function shouldRecord(): bool {
        return $this->samplingDecision->shouldRecord();
    }

    public function traceFlags(): int {
        return $this->samplingDecision->traceFlags();
    }

    public function traceState(): ?TraceStateInterface {
        return $this->traceState;
    }

    public function additionalAttributes(): iterable {
        return $this->additionalAttributes;
    }
}
