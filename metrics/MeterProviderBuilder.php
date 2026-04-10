<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoir;
use Nevay\OTelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\ExplicitBucketHistogramAggregator;
use Nevay\OTelSDK\Metrics\Internal\View\ComposableViewRegistry;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistryBuilder;
use OpenTelemetry\API\Configuration\Context;
use Psr\Log\LoggerInterface;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class MeterProviderBuilder {

    private ?Resource $resource = null;
    /** @var list<MetricReader> */
    private array $metricReaders = [];
    private ExemplarFilter $exemplarFilter = ExemplarFilter::TraceBased;
    private Closure $exemplarReservoir;
    private readonly ViewRegistryBuilder $viewRegistryBuilder;
    private readonly ViewRegistryBuilder $mergeRegistryBuilder;
    /** @var Configurator<MeterConfig>|null */
    private ?Configurator $configurator = null;

    public function __construct() {
        $randomizer = new Randomizer(new PcgOneseq128XslRr64());
        $this->exemplarReservoir = static fn(Aggregator $aggregator) => $aggregator instanceof ExplicitBucketHistogramAggregator && $aggregator->boundaries
            ? new AlignedHistogramBucketExemplarReservoir($aggregator->boundaries, $randomizer)
            : new SimpleFixedSizeExemplarReservoir(1, $randomizer);
        $this->viewRegistryBuilder = new ViewRegistryBuilder();
        $this->mergeRegistryBuilder = new ViewRegistryBuilder();
    }

    public function setResource(Resource $resource): self {
        $this->resource = $resource;

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
     * @param View $view parameters defining the telemetry pipeline
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
     * Customizes telemetry pipelines.
     *
     * @param View $view parameters defining the telemetry pipeline
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
     *
     * @experimental
     */
    public function addComposableView(
        View $view,
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ): self {
        $this->mergeRegistryBuilder->register($view, $type, $name, $unit, $meterName, $meterVersion, $meterSchemaUrl);

        return $this;
    }

    /**
     * @param Configurator<MeterConfig> $configurator
     *
     * @experimental
     */
    public function setMeterConfigurator(Configurator $configurator): self {
        $this->configurator = $configurator;

        return $this;
    }

    public function build(LoggerInterface|Context|null $selfDiagnostics = null, MeterProvider $meterProvider = new MeterProvider()): MeterProviderInterface {
        if ($selfDiagnostics instanceof LoggerInterface) {
            $selfDiagnostics = new Context(logger: $selfDiagnostics);
        }
        if ($selfDiagnostics) {
            $meterProvider->initSelfDiagnostics($selfDiagnostics);
        }

        $meterProvider->update(function(MeterState $state): void {
            $state->configurator = $this->configurator ?? new Configurator\NoopConfigurator();
            $state->metricReaders = $this->metricReaders;
            $state->viewRegistry = new ComposableViewRegistry(
                createViews: $this->viewRegistryBuilder->build(),
                mergeViews: $this->mergeRegistryBuilder->build(),
            );
            $state->exemplarReservoir = $this->exemplarReservoir;
            $state->exemplarFilter = $this->exemplarFilter;
            $state->resource = $this->resource ?? Resource::default();
        });

        return $meterProvider;
    }
}
