<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use OpenTelemetry\API\Trace\SpanInterface;

/**
 * Provides read and write access to spans.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#additional-span-interfaces
 */
interface ReadWriteSpan extends ReadableSpan, SpanInterface {

}
