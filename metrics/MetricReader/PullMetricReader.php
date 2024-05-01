<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricReader;

use Amp\Cancellation;
use InvalidArgumentException;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessor;
use Nevay\OTelSDK\Common\Internal\Export\Listener\NoopListener;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\Internal\MetricExportDriver;
use Nevay\OTelSDK\Metrics\Internal\MultiMetricProducer;
use Nevay\OTelSDK\Metrics\MetricExporter;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Nevay\OTelSDK\Metrics\MetricReader;
use Nevay\OTelSDK\Metrics\MetricReaderAware;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function sprintf;

final class PullMetricReader implements MetricReader {

    private readonly MetricExporter $metricExporter;
    private readonly ExportingProcessor $processor;
    private readonly MultiMetricProducer $metricProducer;

    private bool $closed = false;

    /**
     * @param MetricExporter $metricExporter exporter to push metrics to
     * @param int<0, max> $exportTimeoutMillis export timeout in milliseconds
     * @param int<0, max> $collectTimeoutMillis collect timeout in milliseconds
     * @param MetricFilter|null $metricFilter metric filter to apply to metrics
     *        and attributes during collect
     * @param iterable<MetricProducer> $metricProducers metric producers to
     *        collect metrics from in addition to metrics from the SDK
     * @param TracerProviderInterface $tracerProvider tracer provider for self
     *        diagnostics
     * @param MeterProviderInterface $meterProvider meter provider for self
     *        diagnostics
     * @param LoggerInterface $logger logger for self diagnostics
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(
        MetricExporter $metricExporter,
        int $exportTimeoutMillis = 30000,
        int $collectTimeoutMillis = 30000,
        ?MetricFilter $metricFilter = null,
        iterable $metricProducers = [],
        TracerProviderInterface $tracerProvider = new NoopTracerProvider(),
        MeterProviderInterface $meterProvider = new NoopMeterProvider(),
        LoggerInterface $logger = new NullLogger(),
    ) {
        if ($exportTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Export timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }
        if ($collectTimeoutMillis < 0) {
            throw new InvalidArgumentException(sprintf('Collect timeout (%d) must be greater than or equal to zero', $exportTimeoutMillis));
        }

        $this->metricExporter = $metricExporter;
        $this->metricProducer = new MultiMetricProducer($metricProducers);

        $this->processor = new ExportingProcessor(
            $metricExporter,
            new MetricExportDriver($this->metricProducer, $metricFilter, $collectTimeoutMillis),
            new NoopListener(),
            $exportTimeoutMillis,
            $tracerProvider,
            $meterProvider,
            $logger,
            'metrics',
            'tbachert/otel-sdk-metrics',
        );

        if ($metricExporter instanceof MetricReaderAware) {
            $metricExporter->setMetricReader($this);
        }
    }

    public function __destruct() {
        $this->closed = true;
    }

    public function registerProducer(MetricProducer $metricProducer): void {
        $this->metricProducer->metricProducers[] = $metricProducer;
    }

    public function collect(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->processor->flush()?->await($cancellation);

        return true;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        return $this->metricExporter->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        return $this->metricExporter->forceFlush($cancellation);
    }

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return $this->metricExporter->resolveTemporality($descriptor);
    }

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation {
        return $this->metricExporter->resolveAggregation($instrumentType);
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return $this->metricExporter->resolveCardinalityLimit($instrumentType);
    }
}
