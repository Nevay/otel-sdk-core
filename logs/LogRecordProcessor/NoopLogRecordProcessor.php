<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Nevay\OtelSDK\Logs\LogRecordProcessor;
use Nevay\OtelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\Context\ContextInterface;

final class NoopLogRecordProcessor implements LogRecordProcessor {

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        // no-op
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
