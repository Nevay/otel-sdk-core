<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class Accessor extends LogRecord {

    public static function getContext(LogRecord $logRecord): ?ContextInterface {
        return $logRecord->context;
    }

    public static function getTimestamp(LogRecord $logRecord): ?int {
        return $logRecord->timestamp;
    }

    public static function getObservedTimestamp(LogRecord $logRecord): ?int {
        return $logRecord->observedTimestamp;
    }

    public static function getSeverityText(LogRecord $logRecord): ?string {
        return $logRecord->severityText;
    }

    public static function getSeverityNumber(LogRecord $logRecord): int {
        return $logRecord->severityNumber;
    }

    public static function getBody(LogRecord $logRecord): mixed {
        return $logRecord->body;
    }

    public static function getAttributes(LogRecord $logRecord): iterable {
        return $logRecord->attributes;
    }

    public static function getEventName(LogRecord $logRecord): ?string {
        return $logRecord->eventName ?? null;
    }
}
