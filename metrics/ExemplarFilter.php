<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

/**
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk.md#exemplarfilter
 */
enum ExemplarFilter {

    /**
     * An ExemplarFilter which makes all measurements eligible for being an
     * Exemplar.
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk.md#alwayson
     */
    case AlwaysOn;
    /**
     * An ExemplarFilter which makes no measurements eligible for being an
     * Exemplar.
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk.md#alwaysoff
     */
    case AlwaysOff;
    /**
     * An ExemplarFilter which makes those measurements eligible for being an
     * Exemplar, which are recorded in the context of a sampled parent span.
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk.md#tracebased
     */
    case TraceBased;
}
