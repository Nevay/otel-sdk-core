<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Internal;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceStateInterface;
use function bin2hex;

/**
 * @internal
 * @psalm-immutable
 */
final class SpanContext implements SpanContextInterface {

    public function __construct(
        private readonly string $traceId,
        private readonly string $spanId,
        private readonly int $traceFlags,
        private readonly ?TraceStateInterface $traceState,
    ) {}

    public function getTraceId(): string {
        return bin2hex($this->traceId);
    }

    public function getTraceIdBinary(): string {
        return $this->traceId;
    }

    public function getSpanId(): string {
        return bin2hex($this->spanId);
    }

    public function getSpanIdBinary(): string {
        return $this->spanId;
    }

    public function getTraceFlags(): int {
        return $this->traceFlags;
    }

    public function getTraceState(): ?TraceStateInterface {
        return $this->traceState;
    }

    public function isValid(): bool {
        return true;
    }

    public function isRemote(): bool {
        return false;
    }

    public function isSampled(): bool {
        return (bool) ($this->traceFlags & TraceFlags::SAMPLED);
    }

    # region

    public static function createFromRemoteParent(string $traceId, string $spanId, int $traceFlags = TraceFlags::DEFAULT, ?TraceStateInterface $traceState = null): SpanContextInterface {
        return \OpenTelemetry\API\Trace\SpanContext::createFromRemoteParent($traceId, $spanId, $traceFlags, $traceState);
    }

    public static function create(string $traceId, string $spanId, int $traceFlags = TraceFlags::DEFAULT, ?TraceStateInterface $traceState = null): SpanContextInterface {
        return \OpenTelemetry\API\Trace\SpanContext::create($traceId, $spanId, $traceFlags, $traceState);
    }

    public static function getInvalid(): SpanContextInterface {
        return \OpenTelemetry\API\Trace\SpanContext::getInvalid();
    }

    # endregion
}
