<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Future;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricRegistry;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\StalenessHandlerFactory;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;
use Nevay\OTelSDK\Metrics\MeterConfig;
use Nevay\OTelSDK\Metrics\MeterProviderInterface;
use Nevay\OTelSDK\Metrics\MetricReader;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;
use WeakMap;
use function Amp\async;

/**
 * @internal
 */
final class MeterProvider implements MeterProviderInterface {

    public readonly MeterState $meterState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    /** @var Configurator<MeterConfig> */
    public Configurator $configurator;

    /** @var WeakMap<InstrumentationScope, Meter> */
    private WeakMap $meters;

    /**
     * @param Configurator<MeterConfig> $configurator
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Configurator $configurator,
        Clock $clock,
        AttributesFactory $metricAttributesFactory,
        ExemplarFilter $exemplarFilter,
        Closure $exemplarReservoir,
        ViewRegistry $viewRegistry,
        StalenessHandlerFactory $stalenessHandlerFactory,
        ?LoggerInterface $logger,
    ) {
        $this->meterState = new MeterState(
            new MetricRegistry(
                $contextStorage,
                $metricAttributesFactory,
                $clock,
                $logger,
            ),
            $resource,
            $clock,
            $exemplarFilter,
            $exemplarReservoir,
            $viewRegistry,
            $stalenessHandlerFactory,
            new WeakMap(),
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
        $this->instrumentationScopeCache = new InstrumentationScopeCache();
        $this->configurator = $configurator;
        $this->meters = new WeakMap();
    }

    public function reload(): void {
        foreach ($this->meters as $meter) {
            $config = new MeterConfig();
            $this->configurator->update($config, $meter->instrumentationScope);

            if ($meter->enabled === $config->enabled) {
                continue;
            }

            $this->meterState->logger?->debug('Updating meter configuration', ['scope' => $meter->instrumentationScope, 'config' => $config]);

            $meter->enabled = $config->enabled;
            $this->meterState->updateConfig($config, $meter->instrumentationScope);
        }

        $this->meterState->reload();
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

        if ($meter = $this->meters[$instrumentationScope] ?? null) {
            return $meter;
        }

        $config = new MeterConfig();
        $this->configurator->update($config, $instrumentationScope);

        $this->meterState->logger?->debug('Creating meter', ['scope' => $instrumentationScope, 'config' => $config]);

        return $this->meters[$instrumentationScope] = new Meter(
            $this->meterState,
            $instrumentationScope,
            $config->enabled,
        );
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
