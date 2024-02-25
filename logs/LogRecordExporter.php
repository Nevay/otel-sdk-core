<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Future;
use Nevay\OTelSDK\Common\Internal\Export\Exporter;

/**
 * Exports telemetry data.
 *
 * @extends Exporter<ReadableLogRecord>
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#logrecordexporter
 */
interface LogRecordExporter extends Exporter {

    /**
     * Exports a batch of {@link ReadableLogRecord}s.
     *
     * `Export` will never be called concurrently for the same exporter
     * instance. Exporters can return an unresolved {@link Future} to
     * allow for concurrent exports.
     *
     * @param iterable<ReadableLogRecord> $batch batch of readable log records
     *        to export
     * @param Cancellation|null $cancellation cancellation to abort the export
     * @return Future<bool> whether the export was successful
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#export
     */
    public function export(iterable $batch, ?Cancellation $cancellation = null): Future;

    /**
     * Shuts down the exporter.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the exporter was shut down successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#shutdown-2
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * Force flushes the exporter.
     *
     * This is a hint to ensure that the export of any `ReadableLogRecords` the
     * `LogRecordExporter` has received prior the call to `ForceFlush` SHOULD be
     * completed as soon as possible, preferably before returning from this
     * method.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the exporter was force flushed successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#forceflush-2
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
