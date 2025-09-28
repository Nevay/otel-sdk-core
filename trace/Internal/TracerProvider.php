<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\ConfiguratorStack;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Trace\TracerConfig;
use Nevay\OTelSDK\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TracerProvider implements TracerProviderInterface {

    public readonly TracerState $tracerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    private readonly ConfiguratorStack $tracerConfigurator;

    /**
     * @param ConfiguratorStack<TracerConfig> $tracerConfigurator
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        AttributesFactory $instrumentationScopeAttributesFactory,
        ConfiguratorStack $tracerConfigurator,
        Clock $clock,
        HighResolutionTime $highResolutionTime,
        AttributesFactory $spanAttributesFactory,
        AttributesFactory $eventAttributesFactory,
        AttributesFactory $linkAttributesFactory,
        ?int $eventCountLimit,
        ?int $linkCountLimit,
        ?LoggerInterface $logger,
    ) {
        $this->tracerState = new TracerState(
            $contextStorage,
            $clock,
            $highResolutionTime,
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
        $this->instrumentationScopeCache = new InstrumentationScopeCache();
        $this->tracerConfigurator = $tracerConfigurator;
        $this->tracerConfigurator->onChange(static fn(TracerConfig $tracerConfig, InstrumentationScope $instrumentationScope)
            => $logger?->debug('Updating tracer configuration', ['scope' => $instrumentationScope, 'config' => $tracerConfig]));
    }

    public function updateConfigurator(Configurator|Closure $configurator): void {
        $this->tracerConfigurator->updateConfigurator($configurator);
    }

    public function getTracer(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): TracerInterface {
        if ($name === '') {
            $this->tracerState->logger?->warning('Invalid tracer name', ['name' => $name]);
        }

        $instrumentationScope = new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build());
        $instrumentationScope = $this->instrumentationScopeCache->intern($instrumentationScope);

        $tracerConfig = $this->tracerConfigurator->resolveConfig($instrumentationScope);
        $this->tracerState->logger?->debug('Creating tracer', ['scope' => $instrumentationScope, 'config' => $tracerConfig]);

        return new Tracer($this->tracerState, $instrumentationScope, $tracerConfig);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->forceFlush($cancellation);
    }
}
