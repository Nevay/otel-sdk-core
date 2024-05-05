<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use Composer\InstalledVersions;
use Error;
use Nevay\OTelSDK\Common\Internal\Export\Exception\PermanentExportException;
use Nevay\OTelSDK\Common\Internal\Export\Exception\ResourceExhaustedExportException;
use Nevay\OTelSDK\Common\Internal\Export\Exception\TransientExportException;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use SplQueue;
use Throwable;
use WeakReference;
use function assert;
use function class_exists;

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
    private readonly string $signal;

    private int $processedBatchId = 0;

    /** @var SplQueue<TData> */
    private readonly SplQueue $queue;
    /** @var array<int, DeferredFuture> */
    private array $flush = [];
    private ?Suspension $worker = null;

    private readonly CounterInterface $producerItems;

    private bool $closed = false;

    public function __construct(
        Exporter $exporter,
        ExportingProcessorDriver $driver,
        ExportListener $listener,
        int $exportTimeoutMillis,
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider,
        LoggerInterface $logger,
        string $signal,
        string $package,
    ) {
        $this->exporter = $exporter;
        $this->driver = $driver;
        $this->listener = $listener;
        $this->exportTimeout = $exportTimeoutMillis / 1000;
        $this->signal = $signal;
        $this->queue = new SplQueue();

        $version = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)
            ? InstalledVersions::getPrettyVersion($package)
            : null;
        $tracer = $tracerProvider->getTracer($package, $version);
        $meter = $meterProvider->getMeter($package, $version);

        $reference = WeakReference::create($this);
        $this->workerCallbackId = EventLoop::defer(static fn() => self::worker($reference, $tracer, $logger));

        $this->producerItems = $meter->createCounter(
            'otelsdk_produced_items',
            '{item}',
            'The number of items discarded, dropped, or exported by a SDK pipeline segment.',
            advisory: ['Attributes' => ['otel.success', 'otel.signal', 'otel.outcome']],
        );
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

    public function drop(bool $success, int $count = 1): void {
        $this->producerItems->add($count, ['otel.success' => $success, 'otel.signal' => $this->signal, 'otel.outcome' => 'dropped']);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        try {
            $this->flush()?->await($cancellation);
        } catch (CancelledException $e) {
            $this->drop(success: false, count: $this->drain($e));

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

        $signal = $p->signal;
        $producerItems = $p->producerItems;
        $listener = $p->listener;

        $span = $tracer
            ->spanBuilder('OTel SDK Export')
            ->setAttribute('otel.items', $count)
            ->setAttribute('otel.signal', $signal)
            ->setAttribute('code.function', __FUNCTION__)
            ->setAttribute('code.namespace', __CLASS__)
            ->setAttribute('code.filepath', __FILE__)
            ->setAttribute('code.lineno', __LINE__)
            ->startSpan();
        $context = $span->activate();
        $listener->onExport($count);

        try {
            $future = $p->exporter->export(
                $p->driver->finalize($p->queue->dequeue()),
                new TimeoutCancellation($p->exportTimeout),
            );
        } catch (Throwable $e) {
            $future = Future::error($e);
        } finally {
            $context->detach();
        }

        $future
            ->catch(static fn() => false)
            ->finally(static fn() => $listener->onFinished($count));

        $future
            ->map(static fn(bool $success) => $span
                ->setAttribute('otel.status', 'Ok')
                ->setAttribute('otel.success', $success)
            )
            ->catch(static fn(Throwable $e) => $span
                ->setAttribute('otel.status', 'Error')
                ->setAttribute('otel.success', false)
                ->recordException($e)
            )
            ->finally($span->end(...));
        $future
            ->map(static fn(bool $success) => $producerItems->add($count, ['otel.success' => $success, 'otel.signal' => $signal, 'otel.outcome' => $success ? 'accepted' : 'rejected']))
            ->catch(static fn(Throwable $e) => $producerItems->add($count, ['otel.success' => false, 'otel.signal' => $signal, 'otel.outcome' => self::exceptionToOtelOutcome($e)]));
        $future
            ->catch(static fn(Throwable $e) => $logger->log(self::exceptionToLogLevel($e), 'Exporter threw an exception', ['exception' => $e, 'otel.signal' => $signal]));
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

    private static function exceptionToLogLevel(Throwable $e): string {
        try {
            throw $e;
        } catch (Error) {
            return LogLevel::ERROR;
        } catch (Throwable) {
            return LogLevel::WARNING;
        }
    }

    private static function exceptionToOtelOutcome(Throwable $e): string {
        try {
            throw $e;
        } catch (CancelledException) {
            return 'timeout';
        } catch (ResourceExhaustedExportException) {
            return 'exhausted';
        } catch (TransientExportException) {
            return 'retryable';
        } catch (PermanentExportException | Throwable) {
            return 'rejected';
        }
    }
}
