<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Logs\LoggerConfig;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\API\Trace\Span;

/**
 * @internal
 */
final class Logger implements LoggerInterface {

    public function __construct(
        private readonly LoggerState $loggerState,
        private readonly InstrumentationScope $instrumentationScope,
        private readonly LoggerConfig $loggerConfig,
    ) {}

    public function isEnabled(): bool {
        return !$this->loggerConfig->disabled;
    }

    public function emit(LogRecord $logRecord): void {
        if ($this->loggerConfig->disabled) {
            return;
        }

        $context = ContextResolver::resolve(Accessor::getContext($logRecord), $this->loggerState->contextStorage);

        $record = new ReadWriteLogRecord(
            $this->instrumentationScope,
            $this->loggerState->resource,
            $this->loggerState->logRecordAttributesFactory->builder(),
        );
        $record
            ->setTimestamp(Accessor::getTimestamp($logRecord))
            ->setObservedTimestamp(Accessor::getObservedTimestamp($logRecord))
            ->setSeverityText(Accessor::getSeverityText($logRecord))
            ->setSeverityNumber(Accessor::getSeverityNumber($logRecord))
            ->setAttributes(Accessor::getAttributes($logRecord))
            ->setBody(Accessor::getBody($logRecord))
        ;
        if ($record->getObservedTimestamp() === null) {
            $record->setObservedTimestamp($this->loggerState->clock->now());
        }
        if (($spanContext = Span::fromContext($context)->getContext())->isValid()) {
            $record->setSpanContext($spanContext);
        }

        $this->loggerState->logRecordProcessor->onEmit($record, $context);
    }
}
