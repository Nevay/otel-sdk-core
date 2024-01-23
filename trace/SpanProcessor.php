<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Amp\Cancellation;
use Amp\CancelledException;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * Hook for span start and end method invocations.
 *
 * Span processors are only invoked for recorded spans.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#span-processor
 */
interface SpanProcessor {

    /**
     * Called when a span is started.
     *
     * This method is called synchronously within the
     * {@link SpanBuilderInterface::startSpan()} API, therefore it should not
     * block or throw exceptions.
     *
     * @param ReadWriteSpan $span started span, updates are reflected in it
     * @param ContextInterface $parentContext parent context of the span
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#onstart
     */
    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void;

    /**
     * Called when a span is ended.
     *
     * This method is called synchronously within the
     * {@link SpanInterface::end()} API, therefore it should not block or throw
     * exceptions.
     *
     * @param ReadableSpan $span ended span
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#onendspan
     */
    public function onEnd(ReadableSpan $span): void;

    /**
     * Shuts down the processor.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the processor was shut down successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#shutdown-1
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * Force flushes the processor.
     *
     * This is a hint to ensure that any tasks associated with `Spans` for which
     * the `SpanProcessor` had already received events prior to the call to
     * `ForceFlush` SHOULD be completed as soon as possible, preferably before
     * returning from this method.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the processor was force flushed successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#forceflush-1
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
