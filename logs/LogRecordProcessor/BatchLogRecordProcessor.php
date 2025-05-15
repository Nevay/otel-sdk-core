<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\Export\Driver\BatchDriver;
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
use Revolt\EventLoop;
use function count;

/**
 * `LogRecordProcessor` which creates batches of finished spans and passes them
 * to the configured `LogRecordExporter` after exceeding the configured delay or
 * batch size.
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#batching-processor
 */
final class BatchLogRecordProcessor implements LogRecordProcessor {

    private readonly ExportingProcessor $processor;
    private readonly BatchDriver $driver;
    private readonly QueueSizeListener $listener;
    private readonly int $maxQueueSize;
    private readonly int $maxExportBatchSize;
    private readonly string $scheduledDelayCallbackId;

    private static int $instanceCounter = -1;

    private bool $closed = false;

    /**
     * @param LogRecordExporter $logRecordExporter exporter to push log records
     *        to
     * @param int<0, max> $maxQueueSize maximum number of pending log records
     *        (queued and in-flight), log records exceeding this limit will be
     *        dropped
     * @param int<0, max> $scheduledDelayMillis delay interval in milliseconds
     *        between two consecutive exports if `$maxExportBatchSize` is not
     *        exceeded
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param int<0, max> $maxExportBatchSize maximum batch size of every
     *        export, log records will be exported eagerly after reaching this
     *        limit; must be less than or equal to `maxQueueSize`
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
        int $scheduledDelayMillis = 5000,
        int $exportTimeoutMillis = 30000,
        int $maxExportBatchSize = 512,
        TracerProviderInterface $tracerProvider = new NoopTracerProvider(),
        MeterProviderInterface $meterProvider = new NoopMeterProvider(),
        LoggerInterface $logger = new NullLogger(),
        ?string $name = null,
    ) {
        if ($maxQueueSize < 0) {
            throw new InvalidArgumentException(sprintf('Maximum queue size (%d) must be greater than or equal to zero', $maxQueueSize));
        }
        if ($scheduledDelayMillis < 0) {
            throw new InvalidArgumentException(sprintf('Scheduled delay (%d) must be greater than or equal to zero', $scheduledDelayMillis));
        }
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }
        if ($maxExportBatchSize < 0) {
            throw new InvalidArgumentException(sprintf('Maximum export batch size (%d) must be greater than or equal to zero', $maxExportBatchSize));
        }
        if ($maxExportBatchSize > $maxQueueSize) {
            throw new InvalidArgumentException(sprintf('Maximum export batch size (%d) must be less than or equal to maximum queue size (%d)', $maxExportBatchSize, $maxQueueSize));
        }

        $this->maxQueueSize = $maxQueueSize;
        $this->maxExportBatchSize = $maxExportBatchSize;

        $type = 'batching_logrecord_processor';
        $name ??= $type . '/' . ++self::$instanceCounter;

        $version = InstalledVersions::getVersionRanges('tbachert/otel-sdk-logs');
        $tracer = $tracerProvider->getTracer('com.tobiasbachert.otel.sdk.logs', $version);
        $meter = $meterProvider->getMeter('com.tobiasbachert.otel.sdk.logs', $version);

        $queueSize = $meter->createObservableUpDownCounter(
            'otel.sdk.log.processor.queue_size',
            '{logrecord}',
            'The number of log records in the queue of a given instance of an SDK log record processor',
        );
        $queueCapacity = $meter->createObservableGauge(
            'otel.sdk.log.processor.queue_capacity',
            '{logrecord}',
            'The maximum number of log records the queue of a given instance of an SDK log record processor can hold',
        );
        $processed = $meter->createCounter(
            'otel.sdk.log.processor.logrecords_processed',
            '{logrecord}',
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

        $this->processor = $processor = new ExportingProcessor(
            $logRecordExporter,
            $this->driver = new BatchDriver(),
            $this->listener = new QueueSizeListener(),
            $exportTimeoutMillis,
            $tracer,
            $processed,
            $logger,
            $type,
            $name,
        );
        $this->scheduledDelayCallbackId = EventLoop::disable(EventLoop::unreference(EventLoop::repeat(
            $scheduledDelayMillis / 1000,
            static function() use ($processor): void {
                $processor->flush();
            },
        )));
    }

    public function __destruct() {
        $this->closed = true;
        EventLoop::cancel($this->scheduledDelayCallbackId);
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
        $this->driver->batch[] = $logRecord;

        if (count($this->driver->batch) === 1) {
            EventLoop::enable($this->scheduledDelayCallbackId);
        }
        if (count($this->driver->batch) === $this->maxExportBatchSize) {
            EventLoop::disable($this->scheduledDelayCallbackId);
            $this->processor->enqueue($this->driver->getPending());
        }
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;
        EventLoop::cancel($this->scheduledDelayCallbackId);

        return $this->processor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        EventLoop::disable($this->scheduledDelayCallbackId);

        return $this->processor->forceFlush($cancellation);
    }
}
