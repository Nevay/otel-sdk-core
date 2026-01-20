<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Trace\IdGenerator;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SpanProcessor;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;
use Nevay\OTelSDK\Trace\TracerConfig;
use Nevay\OTelSDK\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;
use WeakMap;

/**
 * @internal
 */
final class TracerProvider implements TracerProviderInterface {

    public readonly TracerState $tracerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    /** @var Configurator<TracerConfig> */
    public Configurator $configurator;
    public SpanSuppressionStrategy $spanSuppressionStrategy;

    /** @var WeakMap<InstrumentationScope, Tracer> */
    private WeakMap $tracers;

    /**
     * @param Configurator<TracerConfig> $configurator
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Configurator $configurator,
        SpanSuppressionStrategy $spanSuppressionStrategy,
        Clock $clock,
        HighResolutionTime $highResolutionTime,
        IdGenerator $idGenerator,
        Sampler $sampler,
        SpanProcessor $spanProcessor,
        SpanListener $spanListener,
        AttributesFactory $spanAttributesFactory,
        AttributesFactory $eventAttributesFactory,
        AttributesFactory $linkAttributesFactory,
        ?int $eventCountLimit,
        ?int $linkCountLimit,
        ?LoggerInterface $logger,
    ) {
        $this->tracerState = new TracerState(
            $contextStorage,
            $resource,
            $clock,
            $highResolutionTime,
            $idGenerator,
            $sampler,
            $spanProcessor,
            $spanListener,
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
        $this->instrumentationScopeCache = new InstrumentationScopeCache();
        $this->configurator = $configurator;
        $this->spanSuppressionStrategy = $spanSuppressionStrategy;
        $this->tracers = new WeakMap();
    }

    public function reload(): void {
        foreach ($this->tracers as $tracer) {
            $config = new TracerConfig();
            $this->configurator->update($config, $tracer->instrumentationScope);

            if ($tracer->enabled === $config->enabled) {
                continue;
            }

            $this->tracerState->logger?->debug('Updating tracer configuration', ['scope' => $tracer->instrumentationScope, 'config' => $config]);

            $tracer->enabled = $config->enabled;
        }
        foreach ($this->tracers as $tracer) {
            $tracer->spanSuppressor = $this->spanSuppressionStrategy->getSuppressor($tracer->instrumentationScope);
        }
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

        if ($tracer = $this->tracers[$instrumentationScope] ?? null) {
            return $tracer;
        }

        $config = new TracerConfig();
        $this->configurator->update($config, $instrumentationScope);

        $this->tracerState->logger?->debug('Creating tracer', ['scope' => $instrumentationScope, 'config' => $config]);

        return $this->tracers[$instrumentationScope] = new Tracer(
            $this->tracerState,
            $instrumentationScope,
            $config->enabled,
            $this->spanSuppressionStrategy->getSuppressor($instrumentationScope),
        );
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->forceFlush($cancellation);
    }
}
