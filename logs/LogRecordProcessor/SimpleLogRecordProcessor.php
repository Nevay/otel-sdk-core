<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs\LogRecordProcessor;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\OtelSDK\Logs\LogRecordExporter;
use Nevay\OtelSDK\Logs\LogRecordProcessor;
use Nevay\OtelSDK\Logs\ReadableLogRecord;
use Nevay\OtelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\Context\ContextInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use SplQueue;
use Throwable;
use WeakReference;
use function assert;

/**
 * `LogRecordProcessor` which passes finished spans to the configured
 * `LogRecordExporter` as soon as they are finished.
 *
 * @see https://opentelemetry.io/docs/specs/otel/logs/sdk/#simple-processor
 */
final class SimpleLogRecordProcessor implements LogRecordProcessor {

    private const ATTRIBUTES_PROCESSOR = ['processor' => 'simple'];
    private const ATTRIBUTES_QUEUED     = self::ATTRIBUTES_PROCESSOR + ['state' => 'queued'];
    private const ATTRIBUTES_PENDING   = self::ATTRIBUTES_PROCESSOR + ['state' => 'pending'];
    private const ATTRIBUTES_PROCESSED = self::ATTRIBUTES_PROCESSOR + ['state' => 'processed'];
    private const ATTRIBUTES_DROPPED   = self::ATTRIBUTES_PROCESSOR + ['state' => 'dropped'];
    private const ATTRIBUTES_SUCCESS   = self::ATTRIBUTES_PROCESSOR + ['state' => 'success'];
    private const ATTRIBUTES_FAILURE   = self::ATTRIBUTES_PROCESSOR + ['state' => 'failure'];
    private const ATTRIBUTES_ERROR     = self::ATTRIBUTES_PROCESSOR + ['state' => 'error'];
    private const ATTRIBUTES_FREE      = self::ATTRIBUTES_PROCESSOR + ['state' => 'free'];

    private readonly LogRecordExporter $logRecordExporter;
    private readonly int $maxQueueSize;
    private readonly float $exportTimeout;
    private readonly string $workerCallbackId;

    private int $dropped = 0;
    private int $processed = 0;
    private int $queueSize = 0;
    private int $processedBatchId = 0;
    /** @var array{0: int<0, max>, 1: int<0, max>} */
    private array $exportResult = [0, 0];
    /** @var SplQueue<ReadableLogRecord> */
    private SplQueue $queue;
    /** @var array<int, DeferredFuture> */
    private array $flush = [];
    private ?Suspension $worker = null;

    private bool $closed = false;

    private ?ObservableCallbackInterface $exportsObserver = null;
    private ?ObservableCallbackInterface $receivedSpansObserver = null;
    private ?ObservableCallbackInterface $queueLimitObserver = null;
    private ?ObservableCallbackInterface $queueUsageObserver = null;

    /**
     * @param LogRecordExporter $logRecordExporter exporter to push log records
     *        to
     * @param int<0, max> $maxQueueSize maximum number of pending log records
     *        (queued and in-flight), log records exceeding this limit will be
     *        dropped
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param Future<MeterProviderInterface>|null $meterProvider meter provider
     *        for self diagnostics
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(
        LogRecordExporter $logRecordExporter,
        int $maxQueueSize = 2048,
        int $exportTimeoutMillis = 30000,
        ?Future $meterProvider = null,
    ) {
        if ($maxQueueSize < 0) {
            throw new InvalidArgumentException(sprintf('Maximum queue size (%d) must be greater than or equal to zero', $maxQueueSize));
        }
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }

        $this->logRecordExporter = $logRecordExporter;
        $this->maxQueueSize = $maxQueueSize;
        $this->exportTimeout = $exportTimeoutMillis / 1000;

        $this->queue = new SplQueue();

        $reference = WeakReference::create($this);
        $this->workerCallbackId = EventLoop::defer(static fn() => self::worker($reference, $meterProvider));
    }

    private function initMetrics(WeakReference $reference, MeterProviderInterface $meterProvider): void {
        $meter = $meterProvider->getMeter('tbachert/otel-sdk-logs',
            InstalledVersions::getPrettyVersion('tbachert/otel-sdk-logs'));

        $this->exportsObserver = $meter
            ->createObservableUpDownCounter(
                'otel.logs.log_record_processor.exports',
                '{exports}',
                'The number of exports handled by the log record processor',
            )
            ->observe(static function(ObserverInterface $observer) use ($reference): void {
                $self = $reference->get();
                assert($self instanceof self);
                $queued = $self->queue->count();
                $pending = $self->processedBatchId - $self->processed;
                $success = $self->exportResult[true];
                $failure = $self->exportResult[false];
                $error = $self->processed - $success - $failure;

                $observer->observe($queued, self::ATTRIBUTES_QUEUED);
                $observer->observe($pending, self::ATTRIBUTES_PENDING);
                $observer->observe($success, self::ATTRIBUTES_SUCCESS);
                $observer->observe($failure, self::ATTRIBUTES_FAILURE);
                $observer->observe($error, self::ATTRIBUTES_ERROR);
            });
        $this->receivedSpansObserver = $meter
            ->createObservableUpDownCounter(
                'otel.logs.log_record_processor.log_records',
                '{logRecords}',
                'The number of log records received by the log record processor',
            )
            ->observe(static function(ObserverInterface $observer) use ($reference): void {
                $self = $reference->get();
                assert($self instanceof self);
                $queued = $self->queue->count();
                $pending = $self->queueSize - $queued;
                $processed = $self->processed;
                $dropped = $self->dropped;

                $observer->observe($queued, self::ATTRIBUTES_QUEUED);
                $observer->observe($pending, self::ATTRIBUTES_PENDING);
                $observer->observe($processed, self::ATTRIBUTES_PROCESSED);
                $observer->observe($dropped, self::ATTRIBUTES_DROPPED);
            });
        $this->queueLimitObserver = $meter
            ->createObservableUpDownCounter(
                'otel.logs.log_record_processor.queue.limit',
                '{logRecords}',
                'The queue size limit',
            )
            ->observe(static function(ObserverInterface $observer) use ($reference): void {
                $self = $reference->get();
                assert($self instanceof self);
                $observer->observe($self->maxQueueSize, self::ATTRIBUTES_PROCESSOR);
            });
        $this->queueUsageObserver = $meter
            ->createObservableUpDownCounter(
                'otel.logs.log_record_processor.queue.usage',
                '{logRecords}',
                'The current queue usage',
            )
            ->observe(static function(ObserverInterface $observer) use ($reference): void {
                $self = $reference->get();
                assert($self instanceof self);
                $queued = $self->queue->count();
                $pending = $self->queueSize - $queued;
                $free = $self->maxQueueSize - $self->queueSize;

                $observer->observe($queued, self::ATTRIBUTES_QUEUED);
                $observer->observe($pending, self::ATTRIBUTES_PENDING);
                $observer->observe($free, self::ATTRIBUTES_FREE);
            });
    }

    public function __destruct() {
        $this->resumeWorker();
        $this->closed = true;
        EventLoop::cancel($this->workerCallbackId);

        $this->exportsObserver?->detach();
        $this->receivedSpansObserver?->detach();
        $this->queueLimitObserver?->detach();
        $this->queueUsageObserver?->detach();
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        if ($this->closed) {
            return;
        }

        if ($this->queueSize === $this->maxQueueSize) {
            $this->dropped++;
            return;
        }

        $this->queueSize++;
        $this->queue->enqueue($logRecord);
        $this->resumeWorker();
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        try {
            $this->flush()?->await($cancellation);
        } finally {
            $success = $this->logRecordExporter->shutdown($cancellation);
        }

        return $success;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        try {
            $this->flush()?->await($cancellation);
        } finally {
            $success = $this->logRecordExporter->forceFlush($cancellation);
        }

        return $success;
    }

    /**
     * @param WeakReference<self> $r
     * @param Future<MeterProviderInterface>|null $meterProvider
     */
    private static function worker(WeakReference $r, ?Future $meterProvider): void {
        $p = $r->get();
        assert($p instanceof self);

        $worker = EventLoop::getSuspension();
        $meterProvider?->map(static fn(MeterProviderInterface $meterProvider) => $p->initMetrics($r, $meterProvider));
        unset($meterProvider);

        do {
            while (!$p->queue->isEmpty()) {
                $id = ++$p->processedBatchId;
                try {
                    $future = $p->logRecordExporter->export(
                        [$p->queue->dequeue()],
                        new TimeoutCancellation($p->exportTimeout),
                    );
                } catch (Throwable $e) {
                    $future = Future::error($e);
                }
                $future
                    ->map(static fn(bool $success) => $p->exportResult[$success]++)
                    ->finally(static function() use ($p): void {
                        $p->processed++;
                        $p->queueSize--;
                    });

                ($p->flush[$id] ?? null)?->complete();
                EventLoop::queue($worker->resume(...));
                unset($p->flush[$id], $future, $e);
                $worker->suspend();
            }

            if ($p->closed) {
                return;
            }

            $p->worker = $worker;
            $p = null;
            $worker->suspend();
        } while ($p = $r->get());
    }

    private function resumeWorker(): void {
        $this->worker?->resume();
        $this->worker = null;
    }

    /**
     * Flushes the batch. The returned future will be resolved after the batch
     * was sent to the exporter.
     */
    private function flush(): ?Future {
        $queued = $this->queue->count();
        if (!$queued) {
            return null;
        }

        $this->resumeWorker();

        return ($this->flush[$this->processedBatchId + $queued] ??= new DeferredFuture())->getFuture();
    }
}
