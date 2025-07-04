<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\Export\Driver\SimpleDriver;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessor;
use Nevay\OTelSDK\Common\Internal\Export\Listener\QueueSizeListener;
use Nevay\OTelSDK\Logs\LogRecordExporter;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Metrics\ObserverInterface;
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

    private static int $instanceCounter = -1;

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
        ?string $name = null,
    ) {
        if ($maxQueueSize < 0) {
            throw new InvalidArgumentException(sprintf('Maximum queue size (%d) must be greater than or equal to zero', $maxQueueSize));
        }
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }

        $this->maxQueueSize = $maxQueueSize;

        $type = 'simple_log_processor';
        $name ??= $type . '/' . ++self::$instanceCounter;

        $version = InstalledVersions::getVersionRanges('tbachert/otel-sdk-logs');
        $tracer = $tracerProvider->getTracer('com.tobiasbachert.otel.sdk.logs', $version, 'https://opentelemetry.io/schemas/1.34.0');
        $meter = $meterProvider->getMeter('com.tobiasbachert.otel.sdk.logs', $version, 'https://opentelemetry.io/schemas/1.34.0');

        $queueSize = $meter->createObservableUpDownCounter(
            'otel.sdk.processor.log.queue.size',
            '{log_record}',
            'The number of log records in the queue of a given instance of an SDK log record processor',
        );
        $queueCapacity = $meter->createObservableGauge(
            'otel.sdk.processor.log.queue.capacity',
            '{log_record}',
            'The maximum number of log records the queue of a given instance of an SDK log record processor can hold',
        );
        $processed = $meter->createCounter(
            'otel.sdk.processor.log.processed',
            '{log_record}',
            'The number of log records for which the processing has finished, either successful or failed',
        );

        $queueSize->observe(fn(ObserverInterface $observer) => $observer->observe(
            $this->listener->queueSize,
            ['otel.sdk.component.name' => $name, 'otel.sdk.component.type' => $type],
        ));
        $queueCapacity->observe(fn(ObserverInterface $observer) => $observer->observe(
            $this->maxQueueSize,
            ['otel.sdk.component.name' => $name, 'otel.sdk.component.type' => $type],
        ));

        $this->processor = new ExportingProcessor(
            $logRecordExporter,
            new SimpleDriver(),
            $this->listener = new QueueSizeListener(),
            $exportTimeoutMillis,
            $tracer,
            $processed,
            $logger,
            $type,
            $name,
        );
    }

    public function __destruct() {
        $this->closed = true;
    }

    public function enabled(ContextInterface $context, InstrumentationScope $instrumentationScope, ?int $severityNumber, ?string $eventName): bool {
        return !$this->closed;
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        if ($this->closed) {
            return;
        }

        if ($this->listener->queueSize === $this->maxQueueSize) {
            $this->processor->drop('queue_full');
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
