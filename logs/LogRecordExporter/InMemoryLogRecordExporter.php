<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Logs\LogRecordExporter;
use Nevay\OTelSDK\Logs\ReadableLogRecord;

final class InMemoryLogRecordExporter implements LogRecordExporter {


    /** @var list<ReadableLogRecord> */
    private array $logRecords = [];

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        foreach ($batch as $logRecord) {
            $this->logRecords[] = $logRecord;
        }

        return Future::complete(true);
    }

    /**
     * @return list<ReadableLogRecord>
     */
    public function collect(bool $reset = false): array {
        $logRecords = $this->logRecords;
        if ($reset) {
            $this->logRecords = [];
        }

        return $logRecords;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
