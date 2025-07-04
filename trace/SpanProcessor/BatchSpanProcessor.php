<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanProcessor;

use Amp\Cancellation;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\Internal\Export\Driver\BatchDriver;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessor;
use Nevay\OTelSDK\Common\Internal\Export\Listener\QueueSizeListener;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\SpanExporter;
use Nevay\OTelSDK\Trace\SpanProcessor;
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
 * `SpanProcessor` which creates batches of finished spans and passes them to
 * the configured `SpanExporter` after exceeding the configured delay or batch
 * size.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#batching-processor
 */
final class BatchSpanProcessor implements SpanProcessor {

    private readonly ExportingProcessor $processor;
    private readonly BatchDriver $driver;
    private readonly QueueSizeListener $listener;
    private readonly int $maxQueueSize;
    private readonly int $maxExportBatchSize;
    private readonly string $scheduledDelayCallbackId;

    private static int $instanceCounter = -1;

    private bool $closed = false;

    /**
     * @param SpanExporter $spanExporter exporter to push spans to
     * @param int<0, max> $maxQueueSize maximum number of pending spans (queued
     *        and in-flight), spans exceeding this limit will be dropped
     * @param int<0, max> $scheduledDelayMillis delay interval in milliseconds
     *        between two consecutive exports if `$maxExportBatchSize` is not
     *        exceeded
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param int<0, max> $maxExportBatchSize maximum batch size of every
     *        export, spans will be exported eagerly after reaching this limit;
     *        must be less than or equal to `maxQueueSize`
     * @param TracerProviderInterface $tracerProvider tracer provider for self
     *        diagnostics
     * @param MeterProviderInterface $meterProvider meter provider for self
     *        diagnostics
     * @param LoggerInterface $logger logger for self diagnostics
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(
        SpanExporter $spanExporter,
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

        $type = 'batching_span_processor';
        $name ??= $type . '/' . ++self::$instanceCounter;

        $version = InstalledVersions::getVersionRanges('tbachert/otel-sdk-trace');
        $tracer = $tracerProvider->getTracer('com.tobiasbachert.otel.sdk.trace', $version, 'https://opentelemetry.io/schemas/1.34.0');
        $meter = $meterProvider->getMeter('com.tobiasbachert.otel.sdk.trace', $version, 'https://opentelemetry.io/schemas/1.34.0');

        $queueSize = $meter->createObservableUpDownCounter(
            'otel.sdk.processor.span.queue.size',
            '{span}',
            'The number of spans in the queue of a given instance of an SDK span processor',
        );
        $queueCapacity = $meter->createObservableGauge(
            'otel.sdk.processor.span.queue.capacity',
            '{span}',
            'The maximum number of spans the queue of a given instance of an SDK span processor can hold',
        );
        $processed = $meter->createCounter(
            'otel.sdk.processor.span.processed',
            '{span}',
            'The number of spans for which the processing has finished, either successful or failed',
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
            $spanExporter,
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

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        // no-op
    }

    public function onEnding(ReadWriteSpan $span): void {
        // no-op
    }

    public function onEnd(ReadableSpan $span): void {
        if ($this->closed) {
            return;
        }
        if (!$span->getContext()->isSampled()) {
            return;
        }

        if ($this->listener->queueSize === $this->maxQueueSize) {
            $this->processor->drop('queue_full');
            return;
        }

        $this->listener->queueSize++;
        $this->driver->batch[] = $span;

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
