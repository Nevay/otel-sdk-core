<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\ConfiguratorStack;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoir;
use Nevay\OTelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\ExplicitBucketHistogramAggregator;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlwaysOffFilter;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlwaysOnFilter;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\TraceBasedFilter;
use Nevay\OTelSDK\Metrics\Internal\MeterMetricProducer;
use Nevay\OTelSDK\Metrics\Internal\MeterProvider;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\DelayedStalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistryBuilder;
use Psr\Log\LoggerInterface;

final class MeterProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<MetricReader> */
    private array $metricReaders = [];
    private ExemplarFilter $exemplarFilter = ExemplarFilter::TraceBased;
    private Closure $exemplarReservoir;
    private readonly ViewRegistryBuilder $viewRegistryBuilder;
    /** @var ConfiguratorStack<MeterConfig> */
    private readonly ConfiguratorStack $meterConfigurator;

    private ?Clock $clock = null;

    public function __construct() {
        $this->exemplarReservoir = static fn(Aggregator $aggregator) => $aggregator instanceof ExplicitBucketHistogramAggregator && $aggregator->boundaries
            ? new AlignedHistogramBucketExemplarReservoir($aggregator->boundaries)
            : new SimpleFixedSizeExemplarReservoir(1);
        $this->viewRegistryBuilder = new ViewRegistryBuilder();
        $this->meterConfigurator = new ConfiguratorStack(
            static fn() => new MeterConfig(),
            static fn(MeterConfig $meterConfig) => $meterConfig->__construct(),
        );
    }

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }

    public function addMetricReader(MetricReader $metricReader): self {
        $this->metricReaders[] = $metricReader;

        return $this;
    }

    public function setExemplarFilter(ExemplarFilter $exemplarFilter): self {
        $this->exemplarFilter = $exemplarFilter;

        return $this;
    }

    /**
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function setExemplarReservoir(Closure $exemplarReservoir): self {
        $this->exemplarReservoir = $exemplarReservoir;

        return $this;
    }

    /**
     * Customizes telemetry pipelines.
     *
     * @param View $view parameters that defines the telemetry pipeline
     * @param InstrumentType|null $type type of instruments to match
     * @param string|null $name name of instruments to match, supports wildcard
     *        patterns:
     *        - `?` matches any single character
     *        - `*` matches any number of any characters including none
     * @param string|null $unit unit of instruments to match
     * @param string|null $meterName name of meters to match
     * @param string|null $meterVersion version of meters to match
     * @param string|null $meterSchemaUrl schema url of meters to match
     *
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#view
     */
    public function addView(
        View $view,
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ): self {
        $this->viewRegistryBuilder->register($view, $type, $name, $unit, $meterName, $meterVersion, $meterSchemaUrl);

        return $this;
    }

    /**
     * @param Configurator<MeterConfig>|Closure(MeterConfig, InstrumentationScope): void $configurator
     *
     * @experimental
     */
    public function addMeterConfigurator(Configurator|Closure $configurator): self {
        $this->meterConfigurator->push($configurator);

        return $this;
    }

    /**
     * @experimental
     */
    public function setClock(Clock&HighResolutionTime $clock): self {
        $this->clock = $clock;

        return $this;
    }

    /**
     * @internal
     */
    public function copyStateInto(MeterProvider $meterProvider): void {
        $meterProvider->meterState->metricReaders = $this->metricReaders;

        foreach ($this->metricReaders as $metricReader) {
            $meterProvider->meterState->metricProducers[] = $metricProducer = new MeterMetricProducer($meterProvider->meterState->registry);
            $metricReader->registerProducer($metricProducer);
        }

        $meterProvider->meterState->exemplarFilter = match ($this->exemplarFilter) {
            ExemplarFilter::AlwaysOn => new AlwaysOnFilter(),
            ExemplarFilter::AlwaysOff => new AlwaysOffFilter(),
            ExemplarFilter::TraceBased => new TraceBasedFilter(),
        };
        $meterProvider->meterState->exemplarReservoir = $this->exemplarReservoir;
        $meterProvider->meterState->viewRegistry = $this->viewRegistryBuilder->build();

        $meterProvider->updateConfigurator(new Configurator\NoopConfigurator());
    }

    /**
     * @internal
     */
    public function buildBase(?LoggerInterface $logger = null): MeterProvider {
        $meterConfigurator = clone $this->meterConfigurator;
        $meterConfigurator->push(static fn(MeterConfig $meterConfig) => $meterConfig->disabled = true);

        $clock = $this->clock ?? SystemClock::create();

        return new MeterProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            $meterConfigurator,
            $clock,
            UnlimitedAttributesFactory::create(),
            new DelayedStalenessHandlerFactory(24 * 60 * 60),
            $logger,
        );
    }

    public function build(?LoggerInterface $logger = null): MeterProviderInterface {
        $meterProvider = $this->buildBase($logger);
        $this->copyStateInto($meterProvider);

        return $meterProvider;
    }
}
