<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace;

use OpenTelemetry\API\Trace\TraceStateInterface;

/**
 * Sampling result returned by a {@link Sampler}.
 */
interface SamplingResult {

    /**
     * Returns whether the span should be recorded.
     *
     * @return bool whether the span should be recorded
     */
    public function shouldRecord(): bool;

    /**
     * Returns trace flags that will be associate with the span.
     *
     * @return int trace flags to associate with the span
     */
    public function traceFlags(): int;

    /**
     * Returns a `TraceState` that will be associated with the span.
     *
     * Can return `null` to use the parent `TraceState`.
     *
     * @return TraceStateInterface|null tracestate to associate with the span, or null to use the
     *         parent tracestate
     */
    public function traceState(): ?TraceStateInterface;

    /**
     * Returns additional attributes that will be associated with the span.
     *
     * @return iterable<string, mixed> additional attributes to associate with the span
     */
    public function additionalAttributes(): iterable;
}
