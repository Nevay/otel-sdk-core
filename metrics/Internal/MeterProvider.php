<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Future;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurable;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\ConfiguratorStack;
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
final class MeterProvider implements MeterProviderInterface, Provider, Configurable {

    private readonly MeterState $meterState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    private readonly ConfiguratorStack $meterConfigurator;

    /**
     * @param ConfiguratorStack<MeterConfig> $meterConfigurator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     * @param list<MetricReader> $metricReaders
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        ConfiguratorStack $meterConfigurator,
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
        $this->meterConfigurator = $meterConfigurator;
        $this->meterConfigurator->onChange(static fn(MeterConfig $meterConfig, InstrumentationScope $instrumentationScope)
            => $logger?->debug('Updating meter configuration', ['scope' => $instrumentationScope, 'config' => $meterConfig]));
        $this->meterConfigurator->onChange($this->meterState->updateConfig(...));
    }

    public function updateConfigurator(Configurator|Closure $configurator): void {
        $this->meterConfigurator->updateConfigurator($configurator);
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

        $meterConfig = $this->meterConfigurator->resolveConfig($instrumentationScope);
        $this->meterState->logger?->debug('Creating meter', ['scope' => $instrumentationScope, 'config' => $meterConfig]);

        return new Meter($this->meterState, $instrumentationScope, $meterConfig);
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
