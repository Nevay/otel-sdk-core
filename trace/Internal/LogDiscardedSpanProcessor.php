<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class LogDiscardedSpanProcessor implements SpanProcessor {

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        // no-op
    }

    public function onEnd(ReadableSpan $span): void {
        if (!$this->hasDroppedData($span)) {
            return;
        }

        $this->logger->info('Span attributes, events or links were discarded due to span limits', [
            'trace_id' => $span->getContext()->getTraceId(),
            'span_id' => $span->getContext()->getSpanId(),
        ]);
    }

    private function hasDroppedData(ReadableSpan $span): bool {
        if ($span->getAttributes()->getDroppedAttributesCount()) {
            return true;
        }
        if ($span->getDroppedEventsCount()) {
            return true;
        }
        if ($span->getDroppedLinksCount()) {
            return true;
        }
        foreach ($span->getEvents() as $event) {
            if ($event->attributes->getDroppedAttributesCount()) {
                return true;
            }
        }
        foreach ($span->getLinks() as $link) {
            if ($link->attributes->getDroppedAttributesCount()) {
                return true;
            }
        }

        return false;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
