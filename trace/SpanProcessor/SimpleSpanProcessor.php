<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanProcessor;

use Amp\Cancellation;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\Internal\Export\Driver\SimpleDriver;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessor;
use Nevay\OTelSDK\Common\Internal\Export\Listener\QueueSizeListener;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\SpanExporter;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * `SpanProcessor` which passes finished spans to the configured `SpanExporter`
 * as soon as they are finished.
 *
 * @see https://opentelemetry.io/docs/specs/otel/trace/sdk/#simple-processor
 */
final class SimpleSpanProcessor implements SpanProcessor {

    private readonly ExportingProcessor $processor;
    private readonly QueueSizeListener $listener;
    private readonly int $maxQueueSize;

    private bool $closed = false;

    /**
     * @param SpanExporter $spanExporter exporter to push spans to
     * @param int<0, max> $maxQueueSize maximum number of pending spans (queued
     *        and in-flight), spans exceeding this limit will be dropped
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
        SpanExporter $spanExporter,
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
            $spanExporter,
            new SimpleDriver(),
            $this->listener = new QueueSizeListener(),
            $exportTimeoutMillis,
            $tracerProvider,
            $meterProvider,
            $logger,
            'traces',
            'tbachert/otel-sdk-trace',
        );
    }

    public function __destruct() {
        $this->closed = true;
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
            $this->processor->drop(success: true);
            return;
        }

        if ($this->listener->queueSize === $this->maxQueueSize) {
            $this->processor->drop(success: false);
            return;
        }

        $this->listener->queueSize++;
        $this->processor->enqueue($span);
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
