<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use SplQueue;
use Throwable;
use WeakReference;
use function assert;

/**
 * @template TData
 *
 * @internal
 */
final class ExportingProcessor {

    private readonly Exporter $exporter;
    private readonly ExportingProcessorDriver $driver;
    private readonly ExportListener $listener;
    private readonly float $exportTimeout;
    private readonly string $workerCallbackId;
    private readonly string $type;
    private readonly string $name;

    private int $processedBatchId = 0;

    /** @var SplQueue<TData> */
    private readonly SplQueue $queue;
    /** @var array<int, DeferredFuture> */
    private array $flush = [];
    private ?Suspension $worker = null;

    private readonly CounterInterface $processedItems;

    private bool $closed = false;

    public function __construct(
        Exporter $exporter,
        ExportingProcessorDriver $driver,
        ExportListener $listener,
        int $exportTimeoutMillis,
        TracerInterface $tracer,
        CounterInterface $processedItems,
        LoggerInterface $logger,
        string $type,
        string $name,
    ) {
        $this->exporter = $exporter;
        $this->driver = $driver;
        $this->listener = $listener;
        $this->exportTimeout = $exportTimeoutMillis / 1000;
        $this->type = $type;
        $this->name = $name;
        $this->queue = new SplQueue();

        $reference = WeakReference::create($this);
        $this->workerCallbackId = EventLoop::defer(static fn() => self::worker($reference, $tracer, $logger));

        $this->processedItems = $processedItems;
    }

    public function __destruct() {
        $this->resumeWorker();
        $this->closed = true;
        EventLoop::cancel($this->workerCallbackId);
    }

    /**
     * @param TData $data
     */
    public function enqueue(mixed $data): void {
        assert(!$this->closed);
        $this->resumeWorker();
        $this->queue->enqueue($data);
    }

    public function drop(string $errorType, int $count = 1): void {
        $this->processedItems->add($count, ['error.type' => $errorType, 'otel.sdk.component.name' => $this->name, 'otel.sdk.component.type' => $this->type]);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        try {
            $this->flush()?->await($cancellation);
        } catch (CancelledException $e) {
            $this->drop(errorType: 'shutdown', count: $this->drain($e));

            throw $e;
        } finally {
            $success = $this->exporter->shutdown($cancellation);
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
            $success = $this->exporter->forceFlush($cancellation);
        }

        return $success;
    }

    /**
     * Drains the internal state, returning the count of drained data.
     *
     * @param CancelledException $e exception that should be used to cancel
     *        pending flush requests
     * @return int count of drained data
     *
     * @see ExportingProcessorDriver::count()
     */
    private function drain(CancelledException $e): int {
        $count = 0;
        while (!$this->queue->isEmpty()) {
            $count += $this->driver->count($this->queue->dequeue());
        }
        if ($this->driver->isBuffered()) {
            $count += $this->driver->count($this->driver->getPending());
        }

        foreach ($this->flush as $flush) {
            $flush->error($e);
        }
        $this->flush = [];

        return $count;
    }

    /**
     * Flushes the batch. The returned future will be resolved after the batch
     * was sent to the exporter.
     */
    public function flush(): ?Future {
        $queued = $this->queue->count() + $this->driver->hasPending();
        if (!$queued) {
            return null;
        }

        $this->resumeWorker();

        return ($this->flush[$this->processedBatchId + $queued] ??= new DeferredFuture())->getFuture()->ignore();
    }

    private function resumeWorker(): void {
        $this->worker?->resume();
        $this->worker = null;
    }

    private static function export(self $p, TracerInterface $tracer, LoggerInterface $logger): void {
        assert(!$p->queue->isEmpty());

        if (!$count = $p->driver->count($p->queue->bottom())) {
            $p->queue->dequeue();
            return;
        }

        $listener = $p->listener;

        $span = $tracer
            ->spanBuilder($p->type)
            ->setAttribute('otel.sdk.component.name', $p->name)
            ->setAttribute('otel.sdk.component.type', $p->type)
            ->setAttribute('code.function', __FUNCTION__)
            ->setAttribute('code.namespace', __CLASS__)
            ->setAttribute('code.filepath', __FILE__)
            ->setAttribute('code.lineno', __LINE__)
            ->startSpan();
        $scope = $span->activate();
        $p->processedItems->add($count, ['otel.sdk.component.name' => $p->name, 'otel.sdk.component.type' => $p->type]);

        $listener->onExport($count);

        try {
            $future = $p->exporter->export(
                $p->driver->finalize($p->queue->dequeue()),
                new TimeoutCancellation($p->exportTimeout),
            );
        } catch (Throwable $e) {
            $future = Future::error($e);
        } finally {
            $scope->detach();
        }

        $future
            ->catch(static fn() => false)
            ->finally(static fn() => $listener->onFinished($count));

        $future
            ->map(static fn(bool $success) => $span
                ->setAttribute('otel.success', $success)
            )
            ->catch(static fn(Throwable $e) => $span
                ->setAttribute('error.type', $e::class)
                ->setAttribute('otel.success', false)
                ->recordException($e)
            )
            ->finally($span->end(...));

        $future->catch(static fn(Throwable $e) => $logger
            ->warning('Exporter threw an exception', ['exception' => $e]));
    }

    private static function worker(WeakReference $r, TracerInterface $tracer, LoggerInterface $logger): void {
        $p = $r->get();
        assert($p instanceof self);

        $worker = EventLoop::getSuspension();
        $pWorker = &$p->worker;

        do {
            while (!$p->queue->isEmpty() || $p->flush) {
                if ($p->queue->isEmpty()) {
                    assert($p->driver->hasPending());
                    $p->queue->push($p->driver->getPending());
                }
                self::export($p, $tracer, $logger);
                $id = ++$p->processedBatchId;
                ($p->flush[$id] ?? null)?->complete();
                unset($p->flush[$id]);
            }

            $p = null;
            if ($r->get()->closed ?? true) {
                break;
            }

            $pWorker = $worker;
            $worker->suspend();
        } while ($p = $r->get());
    }
}
