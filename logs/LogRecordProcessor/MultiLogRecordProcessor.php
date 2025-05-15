<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\Context\ContextInterface;
use function Amp\async;
use function array_key_first;
use function count;

final class MultiLogRecordProcessor implements LogRecordProcessor {

    private readonly iterable $logRecordProcessors;

    /**
     * @param iterable<mixed, LogRecordProcessor> $logRecordProcessors
     */
    private function __construct(iterable $logRecordProcessors) {
        $this->logRecordProcessors = $logRecordProcessors;
    }

    public static function composite(LogRecordProcessor ...$logRecordProcessors): LogRecordProcessor {
        return match (count($logRecordProcessors)) {
            0 => new NoopLogRecordProcessor(),
            1 => $logRecordProcessors[array_key_first($logRecordProcessors)],
            default => new MultiLogRecordProcessor($logRecordProcessors),
        };
    }

    public function enabled(ContextInterface $context, InstrumentationScope $instrumentationScope, ?int $severityNumber, ?string $eventName): bool {
        foreach ($this->logRecordProcessors as $logRecordProcessor) {
            if ($logRecordProcessor->enabled($context, $instrumentationScope, $severityNumber, $eventName)) {
                return true;
            }
        }

        return false;
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        foreach ($this->logRecordProcessors as $logRecordProcessor) {
            $logRecordProcessor->onEmit($logRecord, $context);
        }
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        $futures = [];
        $shutdown = static function(LogRecordProcessor $p, ?Cancellation $cancellation): bool {
            return $p->shutdown($cancellation);
        };
        foreach ($this->logRecordProcessors as $logRecordProcessor) {
            $futures[] = async($shutdown, $logRecordProcessor, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        $futures = [];
        $forceFlush = static function(LogRecordProcessor $p, ?Cancellation $cancellation): bool {
            return $p->forceFlush($cancellation);
        };
        foreach ($this->logRecordProcessors as $logRecordProcessor) {
            $futures[] = async($forceFlush, $logRecordProcessor, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }
}
