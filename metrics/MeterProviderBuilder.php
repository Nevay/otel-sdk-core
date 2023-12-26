<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\AttributesLimitingFactory;
use Nevay\OtelSDK\Common\Provider;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Common\SystemClock;
use Nevay\OtelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OtelSDK\Metrics\Internal\MeterProvider;
use Nevay\OtelSDK\Metrics\Internal\MetricReaderConfiguration;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\DelayedStalenessHandlerFactory;
use Nevay\OtelSDK\Metrics\Internal\View\MutableViewRegistry;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Psr\Log\LoggerInterface;

final class MeterProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<MetricReaderConfiguration> */
    private array $metricReaderConfigurations = [];
    private readonly MutableViewRegistry $viewRegistry;

    public function __construct() {
        $this->viewRegistry = new MutableViewRegistry();
    }

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }

    public function addMetricReader(
        MetricReader $metricReader,
        TemporalityResolver $temporalityResolver = TemporalityResolvers::LowMemory,
        AggregationResolver $aggregationResolver = AggregationResolvers::Default,
        ExemplarReservoirResolver $exemplarReservoirResolver = ExemplarReservoirResolvers::None,
    ): self {
        $this->metricReaderConfigurations[] = new MetricReaderConfiguration($metricReader, $temporalityResolver, $aggregationResolver, $exemplarReservoirResolver);

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

    public function build(?LoggerInterface $logger = null): MeterProviderInterface&Provider {
        return new MeterProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            SystemClock::create(),
            AttributesLimitingFactory::create(),
            $this->metricReaderConfigurations,
            clone $this->viewRegistry,
            new DelayedStalenessHandlerFactory(24 * 60 * 60),
            $logger,
        );
    }
}
