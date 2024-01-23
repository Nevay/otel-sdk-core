<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
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
