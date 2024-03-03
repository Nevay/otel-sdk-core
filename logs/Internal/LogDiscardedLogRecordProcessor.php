<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class LogDiscardedLogRecordProcessor implements LogRecordProcessor {

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        if (!$logRecord->getAttributes()->getDroppedAttributesCount()) {
            return;
        }

        $this->logger->info('Log record attributes were discarded due to log record limits', [
            'log.record.uid' => $logRecord->getAttributes()->get('log.record.uid'),
        ]);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
