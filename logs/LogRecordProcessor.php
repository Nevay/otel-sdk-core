<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs;

use Amp\Cancellation;
use Amp\CancelledException;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * Hook for log record emit invocations.
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#logrecordprocessor
 */
interface LogRecordProcessor {

    /**
     * Called when a log record is emitted.
     *
     * This method is called synchronously within the
     * {@link LoggerInterface::emit()} API, therefore it should not block or
     * throw exceptions.
     *
     * @param ReadWriteLogRecord $logRecord log record, updates are reflected in it
     * @param ContextInterface $context resolved context
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#onemit
     */
    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void;

    /**
     * Shuts down the processor.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the processor was shut down successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#shutdown-1
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * Force flushes the processor.
     *
     * This is a hint to ensure that any tasks associated with `LogRecord`s for
     * which the `LogRecordProcessor` had already received events prior to the
     * call to `ForceFlush` SHOULD be completed as soon as possible, preferably
     * before returning from this method.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the processor was force flushed successfully
     * @throws CancelledException if cancelled by the given cancellation
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#forceflush-1
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
