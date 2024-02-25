<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\Internal\Export\Driver\SimpleDriver;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessor;
use Nevay\OTelSDK\Common\Internal\Export\Listener\QueueSizeListener;
use Nevay\OTelSDK\Logs\LogRecordExporter;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * `LogRecordProcessor` which passes finished spans to the configured
 * `LogRecordExporter` as soon as they are finished.
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#simple-processor
 */
final class SimpleLogRecordProcessor implements LogRecordProcessor {

    private readonly ExportingProcessor $processor;
    private readonly QueueSizeListener $listener;
    private readonly int $maxQueueSize;

    private bool $closed = false;

    /**
     * @param LogRecordExporter $logRecordExporter exporter to push log records
     *        to
     * @param int<0, max> $maxQueueSize maximum number of pending log records
     *        (queued and in-flight), log records exceeding this limit will be
     *        dropped
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param TracerProviderInterface $tracerProvider tracer provider for self
     *        diagnostics
     * @param MeterProviderInterface $meterProvider meter provider for self
     *        diagnostics
     * @param LoggerInterface $logger logger for self diagnostics
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(
        LogRecordExporter $logRecordExporter,
        int $maxQueueSize = 2048,
        int $exportTimeoutMillis = 30000,
        TracerProviderInterface $tracerProvider = new NoopTracerProvider(),
        MeterProviderInterface $meterProvider = new NoopMeterProvider(),
        LoggerInterface $logger = new NullLogger(),
    ) {
        if ($maxQueueSize < 0) {
            throw new InvalidArgumentException(sprintf('Maximum queue size (%d) must be greater than or equal to zero', $maxQueueSize));
        }
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }

        $this->maxQueueSize = $maxQueueSize;

        $this->processor = new ExportingProcessor(
            $logRecordExporter,
            new SimpleDriver(),
            $this->listener = new QueueSizeListener(),
            $exportTimeoutMillis,
            $tracerProvider,
            $meterProvider,
            $logger,
            'logs',
            'tbachert/otel-sdk-logs',
        );
    }

    public function __destruct() {
        $this->closed = true;
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        if ($this->closed) {
            return;
        }

        if ($this->listener->queueSize === $this->maxQueueSize) {
            $this->processor->drop(success: false);
            return;
        }

        $this->listener->queueSize++;
        $this->processor->enqueue($logRecord);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        return $this->processor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        return $this->processor->forceFlush($cancellation);
    }
}
