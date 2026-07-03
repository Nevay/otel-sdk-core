<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use OpenTelemetry\API\Logs\LogRecordBuilderInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\Context\ContextInterface;
use Throwable;

/**
 * @internal
 */
final class NoopLogRecordBuilder implements LogRecordBuilderInterface {

    public function setTimestamp(int $timestamp): LogRecordBuilderInterface {
        return $this;
    }

    public function setObservedTimestamp(int $timestamp): LogRecordBuilderInterface {
        return $this;
    }

    public function setContext(ContextInterface|false|null $context): LogRecordBuilderInterface {
        return $this;
    }

    public function setSeverityNumber(Severity|int $severityNumber): LogRecordBuilderInterface {
        return $this;
    }

    public function setSeverityText(string $severityText): LogRecordBuilderInterface {
        return $this;
    }

    public function setBody(mixed $body): LogRecordBuilderInterface {
        return $this;
    }

    public function setAttribute(string $key, mixed $value): LogRecordBuilderInterface {
        return $this;
    }

    public function setAttributes(iterable $attributes): LogRecordBuilderInterface {
        return $this;
    }

    public function setException(Throwable $exception): LogRecordBuilderInterface {
        return $this;
    }

    public function setEventName(string $eventName): LogRecordBuilderInterface {
        return $this;
    }

    public function emit(): void {
        // no-op
    }
}
