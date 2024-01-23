<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceStateInterface;

/**
 * Basic {@link SamplingResult}s.
 */
enum SamplingDecision implements SamplingResult {

    /**
     * The `Span` wil not be recorded and all events and attributes will be dropped.
     */
    case Drop;
    /**
     * The `Span` will be recorded but the `Sampled` flag will not be set.
     */
    case RecordOnly;
    /**
     * The `Span` will be recorded and the `Sampled` flag will be set.
     */
    case RecordAndSample;

    public function shouldRecord(): bool {
        return match ($this) {
            self::Drop => false,
            self::RecordOnly,
            self::RecordAndSample => true,
        };
    }

    public function traceFlags(): int {
        return match ($this) {
            self::Drop,
            self::RecordOnly => 0,
            self::RecordAndSample => TraceFlags::SAMPLED,
        };
    }

    public function traceState(): ?TraceStateInterface {
        return null;
    }

    public function additionalAttributes(): iterable {
        return [];
    }
}
