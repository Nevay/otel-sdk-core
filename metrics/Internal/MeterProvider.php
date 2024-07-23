<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Future;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OTelSDK\Metrics\MeterConfig;
use Nevay\OTelSDK\Metrics\MetricReader;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;
use WeakMap;
use function Amp\async;

/**
 * @internal
 */
final class MeterProvider implements MeterProviderInterface, Provider {

    private readonly MeterState $meterState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    private readonly WeakMap $configCache;
    private readonly Closure $meterConfigurator;

    /**
     * @param Closure(InstrumentationScope): MeterConfig $meterConfigurator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     * @param list<MetricReader> $metricReaders
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Closure $meterConfigurator,
        Clock $clock,
        AttributesFactory $metricAttributesFactory,
        array $metricReaders,
        ExemplarFilter $exemplarFilter,
        Closure $exemplarReservoir,
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
            $exemplarFilter,
            $exemplarReservoir,
            $viewRegistry,
            $stalenessHandlerFactory,
            new WeakMap(),
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
        $this->instrumentationScopeCache = new InstrumentationScopeCache($logger);
        $this->configCache = new WeakMap();
        $this->meterConfigurator = $meterConfigurator;
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

        $instrumentationScope = new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build());
        $instrumentationScope = $this->instrumentationScopeCache->intern($instrumentationScope);

        /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
        $this->configCache[$instrumentationScope] ??= ($this->meterConfigurator)($instrumentationScope)
            ->onChange(fn(MeterConfig $meterConfig) => $meterConfig->disabled
                ? $this->meterState->disableInstrumentationScope($instrumentationScope)
                : $this->meterState->enableInstrumentationScope($instrumentationScope))
            ->triggerOnChange();

        return new Meter($this->meterState, $instrumentationScope);
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
