<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Common\InstrumentationScope;
use OpenTelemetry\API\Logs\EventLoggerInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class EventLogger implements EventLoggerInterface {

    public function __construct(
        private readonly LoggerState $loggerState,
        private readonly InstrumentationScope $instrumentationScope,
    ) {}

    public function emit(
        string $name,
        mixed $body = null,
        ?int $timestamp = null,
        ContextInterface|false|null $context = null,
        ?Severity $severityNumber = null,
        iterable $attributes = [],
    ): void {
        $context = ContextResolver::resolve($context, $this->loggerState->contextStorage);
        $observedTimestamp = $this->loggerState->clock->now();

        $record = new ReadWriteLogRecord(
            instrumentationScope: $this->instrumentationScope,
            resource: $this->loggerState->resource,
            attributesBuilder: $this->loggerState->logRecordAttributesFactory->builder(),
            timestamp: $timestamp ?? $observedTimestamp,
            observedTimestamp: $observedTimestamp,
            severityNumber: ($severityNumber ?? Severity::INFO)->value,
            body: $body,
        );
        $record
            ->setAttribute('event.name', $name)
            ->setAttributes($attributes)
            ->setAttribute('event.name', $name)
        ;
        if (($spanContext = Span::fromContext($context)->getContext())->isValid()) {
            $record->setSpanContext($spanContext);
        }

        $this->loggerState->logRecordProcessor->onEmit($record, $context);
    }
}
