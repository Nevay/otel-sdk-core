<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Trace\Span\Event;
use Nevay\OtelSDK\Trace\Span\Kind;
use Nevay\OtelSDK\Trace\Span\Link;
use Nevay\OtelSDK\Trace\Span\Status;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * Provides read access to spans.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#additional-span-interfaces
 */
interface ReadableSpan {

    /**
     * Returns the instrumentation scope that created this span.
     *
     * @return InstrumentationScope instrumentation scope that created this span
     */
    public function getInstrumentationScope(): InstrumentationScope;

    /**
     * Returns the resource that is associated with this span.
     *
     * @return Resource resource that is associated with this span
     */
    public function getResource(): Resource;

    /**
     * Returns the span name.
     *
     * @return string span name
     */
    public function getName(): string;

    /**
     * Returns the span context.
     *
     * @return SpanContextInterface span context
     */
    public function getContext(): SpanContextInterface;

    /**
     * Returns the span kind.
     *
     * @return Kind span kind
     */
    public function getSpanKind(): Kind;

    /**
     * Returns the parent span context.
     *
     * The returned span context is ensured to be valid.
     *
     * @return SpanContextInterface|null parent span context, or `null` if this
     *         span has no valid parent context
     */
    public function getParentContext(): ?SpanContextInterface;

    /**
     * Returns the span attributes.
     *
     * @return Attributes span attributes
     */
    public function getAttributes(): Attributes;

    /**
     * Returns the links that were added to the span.
     *
     * @return iterable<int, Link> links
     */
    public function getLinks(): iterable;

    /**
     * Returns the count of dropped links.
     *
     * @return int count of dropped links
     */
    public function getDroppedLinksCount(): int;

    /**
     * Returns the events that were added to the span.
     *
     * @return iterable<int, Event> events
     */
    public function getEvents(): iterable;

    /**
     * Returns the count of dropped events.
     *
     * @return int count of dropped events
     */
    public function getDroppedEventsCount(): int;

    /**
     * Returns the span status.
     *
     * @return Status span status
     */
    public function getStatus(): Status;

    /**
     * Returns the status description.
     *
     * @return string|null status description, or null if the status does not
     *         allow a description or no description was given
     */
    public function getStatusDescription(): ?string;

    /**
     * Returns the start timestamp in nanoseconds.
     *
     * @return int start timestamp in nanoseconds
     */
    public function getStartTimestamp(): int;

    /**
     * Returns the end timestamp in nanoseconds.
     *
     * @return int end timestamp in nanoseconds, or the current timestamp if the
     *         span is still recording
     *
     * @see ReadableSpan::isRecording()
     */
    public function getEndTimestamp(): int;

    /**
     * Returns whether the span is still recording.
     *
     * @return bool true if the span is still recording, false if the span was
     *         ended
     */
    public function isRecording(): bool;
}
