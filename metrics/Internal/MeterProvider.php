<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\ExemplarReservoirResolver;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OTelSDK\Metrics\MetricReader;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;
use WeakMap;
use function Amp\async;

final class MeterProvider implements MeterProviderInterface, Provider {

    private readonly MeterState $meterState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;

    /**
     * @param list<MetricReader> $metricReaders
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Clock $clock,
        AttributesFactory $metricAttributesFactory,
        array $metricReaders,
        ExemplarReservoirResolver $exemplarReservoirResolver,
        ViewRegistry $viewRegistry,
        StalenessHandlerFactory $stalenessHandlerFactory,
        ?LoggerInterface $logger,
    ) {
        $registry = new MetricRegistry($contextStorage, $metricAttributesFactory, $clock, $logger);

        $metricProducers = [];
        foreach ($metricReaders as $metricReader) {
            $metricProducers[] = $metricProducer = new MeterMetricProducer($registry);
            $metricReader->registerProducer($metricProducer);
        }

        $this->meterState = new MeterState(
            $registry,
            $resource,
            $clock,
            $metricReaders,
            $metricProducers,
            $exemplarReservoirResolver,
            $viewRegistry,
            $stalenessHandlerFactory,
            new WeakMap(),
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
    }

    public function getMeter(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): MeterInterface {
        if ($name === '') {
            $this->meterState->logger?->warning('Invalid meter name', ['name' => $name]);
        }

        return new Meter($this->meterState, new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build()));
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        $futures = [];
        $shutdown = static function(MetricReader $r, ?Cancellation $cancellation): bool {
            return $r->shutdown($cancellation);
        };
        foreach ($this->meterState->metricReaders as $metricReader) {
            $futures[] = async($shutdown, $metricReader, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        $futures = [];
        $shutdown = static function(MetricReader $r, ?Cancellation $cancellation): bool {
            return $r->forceFlush($cancellation);
        };
        foreach ($this->meterState->metricReaders as $metricReader) {
            $futures[] = async($shutdown, $metricReader, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }
}
