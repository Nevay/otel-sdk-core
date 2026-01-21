<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Common\StackTrace;
use OpenTelemetry\API\Logs\LogRecordBuilderInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;
use Throwable;

/**
 * @internal
 */
final class LogRecordBuilder implements LogRecordBuilderInterface {

    private readonly Logger $logger;

    private ?int $timestamp = null;
    private ?int $observedTimestamp = null;
    private ContextInterface|false|null $context = null;
    private int $severityNumber = 0;
    private ?string $severityText = null;
    private mixed $body = null;
    private AttributesBuilder $attributesBuilder;
    private ?Throwable $exception = null;
    private ?string $eventName = null;

    public function __construct(
        Logger $logger,
    ) {
        $this->logger = $logger;

        $this->attributesBuilder = $logger->loggerState->logRecordAttributesFactory->builder();
    }

    public function setTimestamp(int $timestamp): LogRecordBuilderInterface {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function setObservedTimestamp(int $timestamp): LogRecordBuilderInterface {
        $this->observedTimestamp = $timestamp;

        return $this;
    }

    public function setContext(ContextInterface|false|null $context): LogRecordBuilderInterface {
        $this->context = $context;

        return $this;
    }

    public function setSeverityNumber(int|Severity $severityNumber): LogRecordBuilderInterface {
        if ($severityNumber instanceof Severity) {
            $severityNumber = $severityNumber->value;
        }

        $this->severityNumber = $severityNumber;

        return $this;
    }

    public function setSeverityText(string $severityText): LogRecordBuilderInterface {
        $this->severityText = $severityText;

        return $this;
    }

    public function setBody(mixed $body): LogRecordBuilderInterface {
        $this->body = $body;

        return $this;
    }

    public function setAttribute(string $key, mixed $value): LogRecordBuilderInterface {
        $this->attributesBuilder->add($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): LogRecordBuilderInterface {
        $this->attributesBuilder->addAll($attributes);

        return $this;
    }

    public function setException(Throwable $exception): LogRecordBuilderInterface {
        $this->exception = $exception;

        return $this;
    }

    public function setEventName(string $eventName): LogRecordBuilderInterface {
        $this->eventName = $eventName;

        return $this;
    }

    public function emit(): void {
        if (!$this->logger->enabled) {
            return;
        }
        if (!$this->logger->filterSeverity($this->severityNumber)) {
            return;
        }

        $context = ContextResolver::resolve($this->context, $this->logger->loggerState->contextStorage);

        if (!$this->logger->filterTraceBased($context)) {
            return;
        }

        $attributesBuilder = clone $this->attributesBuilder;

        if ($this->exception) {
            $attributes = $attributesBuilder->build();

            if (!$attributes->has('exception.message')) {
                $attributesBuilder->add('exception.message', $this->exception->getMessage());
            }
            if (!$attributes->has('exception.type')) {
                $attributesBuilder->add('exception.type', $this->exception::class);
            }
            if (!$attributes->has('exception.stacktrace')) {
                $attributesBuilder->add('exception.stacktrace', StackTrace::format($this->exception, StackTrace::DOT_SEPARATOR));
            }
        }

        $record = new ReadWriteLogRecord(
            $this->logger->instrumentationScope,
            $this->logger->loggerState->resource,
            $attributesBuilder,
            $this->timestamp,
            $this->observedTimestamp,
            null,
            $this->severityText,
            $this->severityNumber,
            $this->body,
            $this->eventName,
        );

        if ($record->getObservedTimestamp() === null) {
            $record->setObservedTimestamp($this->logger->loggerState->clock->now());
        }
        if (($spanContext = Span::fromContext($context)->getContext())->isValid()) {
            $record->setSpanContext($spanContext);
        }

        $this->logger->loggerState->logRecordProcessor->onEmit($record, $context);
    }
}
