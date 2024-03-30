<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

/**
 * Generator for trace ids and span ids.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#id-generators
 */
interface IdGenerator {

    /**
     * Generates a new span id.
     *
     * @return string non-zero span id in binary format
     */
    public function generateSpanIdBinary(): string;

    /**
     * Generates a new trace id.
     *
     * @return string non-zero trace id in binary format
     */
    public function generateTraceIdBinary(): string;

    /**
     * Returns trace flags that should be associated with new traces.
     *
     * @return int trace flags to associate with new traces
     */
    public function traceFlags(): int;
}
