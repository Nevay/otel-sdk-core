<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;

/**
 * @experimental
 */
final class EventToSpanEventBridge implements LogRecordProcessor {

    public function enabled(ContextInterface $context, InstrumentationScope $instrumentationScope, ?int $severityNumber, ?string $eventName): bool {
        return $eventName !== null && Span::fromContext($context)->isRecording();
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        $eventName = $logRecord->getEventName();
        if ($eventName === null) {
            return;
        }

        $span = Span::fromContext($context);
        if (!$span->isRecording()) {
            return;
        }

        if ($span->getContext()->getTraceIdBinary() !== $logRecord->getSpanContext()?->getTraceIdBinary()) {
            return;
        }
        if ($span->getContext()->getSpanIdBinary() !== $logRecord->getSpanContext()?->getSpanIdBinary()) {
            return;
        }

        $attributes = $logRecord->getAttributes()->toArray();
        $timestamp = $logRecord->getTimestamp() ?? $logRecord->getObservedTimestamp();

        $span->addEvent($eventName, $attributes, $timestamp);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
