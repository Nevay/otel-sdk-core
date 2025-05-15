<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Amp\Cancellation;
use Amp\CancelledException;
use Nevay\OTelSDK\Common\InstrumentationScope;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * Hook for log record emit invocations.
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#logrecordprocessor
 */
interface LogRecordProcessor {

    /**
     * May return false to filter out log records.
     *
     * `LogRecordProcessor` implementations responsible for filtering and supporting the `Enabled` operation should
     * ensure that `OnEmit` handles filtering independently. API users cannot be expected to call `Enabled` before
     * emitting a log record.
     *
     * @param ContextInterface $context the context passed by the caller or the current context
     * @param InstrumentationScope $instrumentationScope instrumentation scope associated with the logger
     * @param int|null $severityNumber severity number passed by the caller
     * @param string|null $eventName event name passed by the caller
     * @return bool whether a log record should be filtered out for the given parameters
     *
     * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#enabled-1
     */
    public function enabled(ContextInterface $context, InstrumentationScope $instrumentationScope, ?int $severityNumber, ?string $eventName): bool;

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
