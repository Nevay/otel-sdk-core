<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\SystemHighResolutionTime;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Trace\IdGenerator\RandomIdGenerator;
use Nevay\OTelSDK\Trace\Internal\LogDiscardedSpanProcessor;
use Nevay\OTelSDK\Trace\Internal\NoopSpanListener;
use Nevay\OTelSDK\Trace\Internal\SelfDiagnosticsSpanProcessor;
use Nevay\OTelSDK\Trace\Sampler\Composable\ComposableAlwaysOnSampler;
use Nevay\OTelSDK\Trace\Sampler\Composable\ComposableParentThresholdSampler;
use Nevay\OTelSDK\Trace\Sampler\CompositeSampler;
use Nevay\OTelSDK\Trace\SpanProcessor\MultiSpanProcessor;
use Nevay\OTelSDK\Trace\SpanProcessor\NoopSpanProcessor;
use Nevay\OTelSDK\Trace\SpanSuppression\NoopSuppressionStrategy;
use OpenTelemetry\API\Configuration\Context;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\NullLogger;
use function array_values;

final class TracerProvider implements TracerProviderInterface {

    private readonly Internal\TracerProvider $tracerProvider;

    /** @var array<SpanProcessor> */
    private array $spanProcessors = [];
    /** @var list<SpanProcessor> */
    private array $diagnosticSpanProcessors = [];

    public function __construct((Clock&HighResolutionTime)|null $clock = null) {
        $this->tracerProvider = new Internal\TracerProvider(
            null,
            Resource::default(),
            UnlimitedAttributesFactory::create(),
            new Configurator\NoopConfigurator(),
            new NoopSuppressionStrategy(),
            $clock ?? SystemClock::create(),
            $clock ?? SystemHighResolutionTime::create(),
            new RandomIdGenerator(),
            new CompositeSampler(new ComposableParentThresholdSampler(new ComposableAlwaysOnSampler())),
            new NoopSpanProcessor(),
            new NoopSpanListener(),
            AttributesLimitingFactory::create(),
            AttributesLimitingFactory::create(),
            AttributesLimitingFactory::create(),
            128,
            128,
            new NullLogger(),
        );
    }

    public function update(Closure $update): void {
        $state = new TracerState(
            configurator: $this->tracerProvider->configurator,
            idGenerator: $this->tracerProvider->tracerState->idGenerator,
            sampler: $this->tracerProvider->tracerState->sampler,
            spanProcessors: $this->spanProcessors,
            resource: $this->tracerProvider->tracerState->resource,
            spanAttributesFactory: $this->tracerProvider->tracerState->spanAttributesFactory,
            eventAttributesFactory: $this->tracerProvider->tracerState->eventAttributesFactory,
            linkAttributesFactory: $this->tracerProvider->tracerState->linkAttributesFactory,
            eventCountLimit: $this->tracerProvider->tracerState->eventCountLimit,
            linkCountLimit: $this->tracerProvider->tracerState->linkCountLimit,
            spanSuppressionStrategy: $this->tracerProvider->spanSuppressionStrategy,
        );

        $update($state);

        $this->spanProcessors = $state->spanProcessors;

        $this->tracerProvider->tracerState->resource = $state->resource;
        $this->tracerProvider->tracerState->idGenerator = $state->idGenerator;
        $this->tracerProvider->tracerState->sampler = $state->sampler;
        $this->tracerProvider->tracerState->spanProcessor = MultiSpanProcessor::composite(...array_values($this->spanProcessors), ...$this->diagnosticSpanProcessors);
        $this->tracerProvider->tracerState->spanAttributesFactory = $state->spanAttributesFactory;
        $this->tracerProvider->tracerState->eventAttributesFactory = $state->eventAttributesFactory;
        $this->tracerProvider->tracerState->linkAttributesFactory = $state->linkAttributesFactory;
        $this->tracerProvider->tracerState->eventCountLimit = $state->eventCountLimit;
        $this->tracerProvider->tracerState->linkCountLimit = $state->linkCountLimit;

        $this->tracerProvider->configurator = $state->configurator;
        $this->tracerProvider->spanSuppressionStrategy = $state->spanSuppressionStrategy;

        $this->tracerProvider->reload();
    }

    /**
     * @internal
     */
    public function initSelfDiagnostics(Context $selfDiagnostics): void {
        $logDiscardedSpanProcessor = new LogDiscardedSpanProcessor($selfDiagnostics->logger);
        $selfDiagnosticsSpanProcessor = new SelfDiagnosticsSpanProcessor($selfDiagnostics->meterProvider);

        $this->diagnosticSpanProcessors = [
            $logDiscardedSpanProcessor,
            $selfDiagnosticsSpanProcessor,
        ];

        $this->tracerProvider->tracerState->spanProcessor = MultiSpanProcessor::composite(...array_values($this->spanProcessors), ...$this->diagnosticSpanProcessors);
        $this->tracerProvider->tracerState->spanListener = $selfDiagnosticsSpanProcessor;
        $this->tracerProvider->tracerState->logger = $selfDiagnostics->logger;
    }

    public function getTracer(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): TracerInterface {
        return $this->tracerProvider->getTracer($name, $version, $schemaUrl, $attributes);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->tracerProvider->forceFlush($cancellation);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->tracerProvider->shutdown($cancellation);
    }
}
