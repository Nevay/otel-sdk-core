<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlwaysOffFilter;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlwaysOnFilter;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarReservoirs;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\TraceBasedFilter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\DelayedStalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\DefaultViewRegistry;
use OpenTelemetry\API\Configuration\Context;
use OpenTelemetry\API\Metrics\MeterInterface;
use Psr\Log\NullLogger;
use function spl_object_id;

final class MeterProvider implements MeterProviderInterface {

    private readonly Internal\MeterProvider $meterProvider;

    private Resource $resource;
    private ExemplarFilter $exemplarFilter = ExemplarFilter::AlwaysOff;
    /** @var array<MetricReader> */
    private array $metricReaders = [];

    public function __construct(?Clock $clock = null) {
        $this->meterProvider = new Internal\MeterProvider(
            null,
            UnlimitedAttributesFactory::create(),
            new Configurator\NoopConfigurator(),
            $clock ?? SystemClock::create(),
            UnlimitedAttributesFactory::create(),
            new AlwaysOffFilter(),
            ExemplarReservoirs::defaultFactory(),
            new DefaultViewRegistry(),
            new DelayedStalenessHandlerFactory(24 * 60 * 60),
            new NullLogger(),
        );
        $this->resource = Resource::default();
    }

    public function update(Closure $update): void {
        $state = new MeterState(
            configurator: $this->meterProvider->configurator,
            metricReaders: $this->metricReaders,
            viewRegistry: $this->meterProvider->meterState->viewRegistry,
            exemplarReservoir: $this->meterProvider->meterState->exemplarReservoir,
            exemplarFilter: $this->exemplarFilter,
            resource: $this->resource,
        );

        $update($state);

        $previousMetricReaders = $this->metricReaders;
        $this->metricReaders = $state->metricReaders;
        $this->exemplarFilter = $state->exemplarFilter;

        $this->meterProvider->meterState->exemplarFilter = match ($this->exemplarFilter) {
            ExemplarFilter::AlwaysOn => new AlwaysOnFilter(),
            ExemplarFilter::AlwaysOff => new AlwaysOffFilter(),
            ExemplarFilter::TraceBased => new TraceBasedFilter(),
        };
        $this->meterProvider->meterState->exemplarReservoir = $state->exemplarReservoir;
        $this->meterProvider->meterState->viewRegistry = $state->viewRegistry;

        $this->meterProvider->configurator = $state->configurator;

        $metricReaderIds = [];
        foreach ($state->metricReaders as $metricReader) {
            $metricReaderIds[spl_object_id($metricReader)] = true;
            $metricReader->updateResource($this->resource);
            $this->meterProvider->meterState->register($metricReader);
        }
        foreach ($previousMetricReaders as $metricReader) {
            if (isset($metricReaderIds[spl_object_id($metricReader)])) {
                continue;
            }
            $this->meterProvider->meterState->unregister($metricReader);
        }

        $this->meterProvider->reload();
    }

    /**
     * @internal
     */
    public function initSelfDiagnostics(Context $selfDiagnostics): void {
        $this->meterProvider->meterState->logger = $selfDiagnostics->logger;
    }

    public function getMeter(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): MeterInterface {
        return $this->meterProvider->getMeter($name, $version, $schemaUrl, $attributes);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->meterProvider->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->meterProvider->forceFlush($cancellation);
    }
}
