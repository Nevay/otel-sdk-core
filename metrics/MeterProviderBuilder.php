<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Metrics\Internal\MeterProvider;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\DelayedStalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\MutableViewRegistry;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Psr\Log\LoggerInterface;

final class MeterProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<MetricReader> */
    private array $metricReaders = [];
    private ExemplarReservoirResolver $exemplarReservoirResolver = ExemplarReservoirResolvers::None;
    private readonly MutableViewRegistry $viewRegistry;

    private ?Closure $meterConfigurator = null;


    public function __construct() {
        $this->viewRegistry = new MutableViewRegistry();
    }

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }
    public function addMetricReader(MetricReader $metricReader): self {
        $this->metricReaders[] = $metricReader;

        return $this;
    }

    public function setExemplarReservoirResolver(ExemplarReservoirResolver $exemplarReservoirResolver): self {
        $this->exemplarReservoirResolver = $exemplarReservoirResolver;

        return $this;
    }

    public function addView(
        View $view,
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ): self {
        $this->viewRegistry->register($view, $type, $name, $unit, $meterName, $meterVersion, $meterSchemaUrl);

        return $this;
    }

    /**
     * @param Closure(InstrumentationScope): MeterConfig $meterConfigurator
     *
     * @experimental
     */
    public function setMeterConfigurator(Closure $meterConfigurator): self {
        $this->meterConfigurator = $meterConfigurator;

        return $this;
    }

    public function build(?LoggerInterface $logger = null): MeterProviderInterface&Provider {
        $meterConfigurator = $this->meterConfigurator
            ?? static fn(InstrumentationScope $instrumentationScope): MeterConfig => new MeterConfig();

        return new MeterProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            $meterConfigurator,
            SystemClock::create(),
            UnlimitedAttributesFactory::create(),
            $this->metricReaders,
            $this->exemplarReservoirResolver,
            clone $this->viewRegistry,
            new DelayedStalenessHandlerFactory(24 * 60 * 60),
            $logger,
        );
    }
}
