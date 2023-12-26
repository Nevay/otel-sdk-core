<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\MetricReader;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\OtelSDK\Metrics\Internal\MultiMetricProducer;
use Nevay\OtelSDK\Metrics\MetricExporter;
use Nevay\OtelSDK\Metrics\MetricFilter;
use Nevay\OtelSDK\Metrics\MetricProducer;
use Nevay\OtelSDK\Metrics\MetricReader;
use Nevay\OtelSDK\Metrics\MetricReaderAware;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use Throwable;
use WeakReference;
use function assert;
use function sprintf;

final class PullMetricReader implements MetricReader {

    private const ATTRIBUTES_PROCESSOR = ['reader' => 'pull'];
    private const ATTRIBUTES_PENDING   = self::ATTRIBUTES_PROCESSOR + ['state' => 'pending'];
    private const ATTRIBUTES_SUCCESS   = self::ATTRIBUTES_PROCESSOR + ['state' => 'success'];
    private const ATTRIBUTES_FAILURE   = self::ATTRIBUTES_PROCESSOR + ['state' => 'failure'];
    private const ATTRIBUTES_ERROR     = self::ATTRIBUTES_PROCESSOR + ['state' => 'error'];

    private readonly MetricExporter $metricExporter;
    private readonly float $exportTimeout;
    private readonly float $collectTimeout;
    private readonly ?MetricFilter $metricFilter;
    private readonly MultiMetricProducer $metricProducer;
    private readonly string $workerCallbackId;

    private int $processed = 0;
    private int $processedBatchId = 0;
    /** @var array{0: int<0, max>, 1: int<0, max>} */
    private array $exportResult = [0, 0];
    /** @var array<int, DeferredFuture> */
    private array $flush = [];
    private ?Suspension $worker = null;

    private bool $closed = false;

    private ?ObservableCallbackInterface $exportsObserver = null;

    /**
     * @param MetricExporter $metricExporter exporter to push metrics to
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param int<0, max> $collectTimeoutMillis collect timeout in milliseconds
     * @param MetricFilter|null $metricFilter metric filter to apply to metrics
     *        and attributes during collect
     * @param Future<MeterProviderInterface>|null $meterProvider meter provider
     *        for self diagnostics
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(
        MetricExporter $metricExporter,
        int $exportTimeoutMillis = 30000,
        int $collectTimeoutMillis = 30000,
        ?MetricFilter $metricFilter = null,
        ?Future $meterProvider = null,
    ) {
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }
        if ($collectTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Collect timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }

        $this->metricExporter = $metricExporter;
        $this->exportTimeout = $exportTimeoutMillis / 1000;
        $this->collectTimeout = $collectTimeoutMillis / 1000;
        $this->metricFilter = $metricFilter;
        $this->metricProducer = new MultiMetricProducer();

        $reference = WeakReference::create($this);
        $this->workerCallbackId = EventLoop::defer(static fn() => self::worker($reference, $meterProvider));

        if ($metricExporter instanceof MetricReaderAware) {
            $metricExporter->setMetricReader($this);
        }
    }

    private function initMetrics(WeakReference $reference, MeterProviderInterface $meterProvider): void {
        $meter = $meterProvider->getMeter('tbachert/otel-sdk-metrics',
            InstalledVersions::getPrettyVersion('tbachert/otel-sdk-metrics'));

        $this->exportsObserver = $meter
            ->createObservableUpDownCounter(
                'otel.metrics.metric_reader.exports',
                '{exports}',
                'The number of exports handled by the metric reader',
            )
            ->observe(static function(ObserverInterface $observer) use ($reference): void {
                $self = $reference->get();
                assert($self instanceof self);
                $pending = $self->processedBatchId - $self->processed;
                $success = $self->exportResult[true];
                $failure = $self->exportResult[false];
                $error = $self->processed - $success - $failure;

                $observer->observe($pending, self::ATTRIBUTES_PENDING);
                $observer->observe($success, self::ATTRIBUTES_SUCCESS);
                $observer->observe($failure, self::ATTRIBUTES_FAILURE);
                $observer->observe($error, self::ATTRIBUTES_ERROR);
            });
    }

    public function __destruct() {
        $this->resumeWorker();
        $this->closed = true;
        EventLoop::cancel($this->workerCallbackId);

        $this->exportsObserver?->detach();
    }

    public function registerProducer(MetricProducer $metricProducer): void {
        $this->metricProducer->metricProducers[] = $metricProducer;
    }

    public function collect(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->flush()->await($cancellation);

        return true;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        return true;
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
            while ($p->flush) {
                $id = ++$p->processedBatchId;
                try {
                    $future = $p->metricExporter->export(
                        $p->metricProducer->produce($p->metricFilter, new TimeoutCancellation($p->collectTimeout)),
                        new TimeoutCancellation($p->exportTimeout),
                    );
                } catch (Throwable $e) {
                    $future = Future::error($e);
                }
                $future = $future
                    ->map(static fn(bool $success) => $p->exportResult[$success]++)
                    ->finally(static fn() => $p->processed++);

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
    private function flush(): Future {
        $this->resumeWorker();

        return ($this->flush[$this->processedBatchId + 1] ??= new DeferredFuture())->getFuture();
    }
}
