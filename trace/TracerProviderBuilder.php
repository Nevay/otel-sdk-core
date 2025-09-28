<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\ConfiguratorStack;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\SystemHighResolutionTime;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Trace\IdGenerator\RandomIdGenerator;
use Nevay\OTelSDK\Trace\Internal\LogDiscardedSpanProcessor;
use Nevay\OTelSDK\Trace\Internal\SelfDiagnosticsSpanProcessor;
use Nevay\OTelSDK\Trace\Internal\TracerProvider;
use Nevay\OTelSDK\Trace\Sampler\AlwaysOnSampler;
use Nevay\OTelSDK\Trace\Sampler\ParentBasedSampler;
use Nevay\OTelSDK\Trace\SpanProcessor\MultiSpanProcessor;
use Nevay\OTelSDK\Trace\SpanSuppression\NoopSuppressionStrategy;
use OpenTelemetry\API\Configuration\Context;
use Psr\Log\LoggerInterface;

final class TracerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<SpanProcessor> */
    private array $spanProcessors = [];

    private ?IdGenerator $idGenerator = null;
    private ?Sampler $sampler = null;
    /** @var ConfiguratorStack<TracerConfig> */
    private readonly ConfiguratorStack $tracerConfigurator;
    private ?SpanSuppressionStrategy $spanSuppressionStrategy = null;

    private ?Clock $clock = null;
    private ?HighResolutionTime $highResolutionTime = null;

    private ?int $attributeCountLimit = null;
    private ?int $attributeValueLengthLimit = null;
    private ?int $spanAttributeCountLimit = null;
    private ?int $spanAttributeValueLengthLimit = null;
    private ?int $eventAttributeCountLimit = null;
    private ?int $eventAttributeValueLengthLimit = null;
    private ?int $linkAttributeCountLimit = null;
    private ?int $linkAttributeValueLengthLimit = null;
    private ?int $eventCountLimit = null;
    private ?int $linkCountLimit = null;

    public function __construct() {
        $this->tracerConfigurator = new ConfiguratorStack(
            static fn() => new TracerConfig(),
            static fn(TracerConfig $tracerConfig) => $tracerConfig->__construct(),
        );
    }

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }

    public function addSpanProcessor(SpanProcessor $spanProcessor): self {
        $this->spanProcessors[] = $spanProcessor;

        return $this;
    }

    public function setIdGenerator(?IdGenerator $idGenerator): self {
        $this->idGenerator = $idGenerator;

        return $this;
    }

    public function setSampler(?Sampler $sampler): self {
        $this->sampler = $sampler;

        return $this;
    }

    /**
     * @param Configurator<TracerConfig>|Closure(TracerConfig, InstrumentationScope): void $configurator
     *
     * @experimental
     */
    public function addTracerConfigurator(Configurator|Closure $configurator): self {
        $this->tracerConfigurator->push($configurator);

        return $this;
    }

    /**
     * @experimental
     */
    public function setSuppressionStrategy(SpanSuppressionStrategy $strategy): self {
        $this->spanSuppressionStrategy = $strategy;

        return $this;
    }

    public function setAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->attributeCountLimit = $attributeCountLimit;
        $this->attributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setSpanAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->spanAttributeCountLimit = $attributeCountLimit;
        $this->spanAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setEventAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->eventAttributeCountLimit = $attributeCountLimit;
        $this->eventAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setLinkAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->linkAttributeCountLimit = $attributeCountLimit;
        $this->linkAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setEventCountLimit(?int $eventCountLimit): self {
        $this->eventCountLimit = $eventCountLimit;

        return $this;
    }

    public function setLinkCountLimit(?int $linkCountLimit): self {
        $this->linkCountLimit = $linkCountLimit;

        return $this;
    }

    /**
     * @experimental
     */
    public function setClock(Clock&HighResolutionTime $clock): self {
        $this->clock = $clock;
        $this->highResolutionTime = $clock;

        return $this;
    }

    /**
     * @internal
     */
    public function copyStateInto(TracerProvider $tracerProvider, Context $selfDiagnostics): void {
        $resource = Resource::mergeAll(...$this->resources);
        $idGenerator = $this->idGenerator ?? new RandomIdGenerator();
        $sampler = $this->sampler ?? new ParentBasedSampler(new AlwaysOnSampler());
        $spanProcessors = $this->spanProcessors;
        if ($tracerProvider->tracerState->logger) {
            $spanProcessors[] = new LogDiscardedSpanProcessor($tracerProvider->tracerState->logger);
        }

        $tracerProvider->tracerState->spanListener = $spanProcessors[] = new SelfDiagnosticsSpanProcessor($selfDiagnostics->meterProvider);
        $tracerProvider->tracerState->resource = $resource;
        $tracerProvider->tracerState->idGenerator = $idGenerator;
        $tracerProvider->tracerState->sampler = $sampler;
        $tracerProvider->tracerState->spanProcessor = MultiSpanProcessor::composite(...$spanProcessors);

        $tracerProvider->updateConfigurator(new Configurator\NoopConfigurator());
    }

    /**
     * @internal
     */
    public function buildBase(?LoggerInterface $logger = null): TracerProvider {
        $spanAttributesFactory = AttributesLimitingFactory::create(
            $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $eventAttributesFactory = AttributesLimitingFactory::create(
            $this->eventAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->eventAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $linkAttributesFactory = AttributesLimitingFactory::create(
            $this->linkAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->linkAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $eventCountLimit = $this->eventCountLimit ?? 128;
        $linkCountLimit = $this->linkCountLimit ?? 128;

        $tracerConfigurator = clone $this->tracerConfigurator;
        $tracerConfigurator->push(static fn(TracerConfig $tracerConfig) => $tracerConfig->disabled = true);

        $spanSuppressionStrategy = $this->spanSuppressionStrategy ?? new NoopSuppressionStrategy();

        $clock = $this->clock ?? SystemClock::create();
        $highResolutionTime = $this->highResolutionTime ?? SystemHighResolutionTime::create();

        return new TracerProvider(
            null,
            UnlimitedAttributesFactory::create(),
            $tracerConfigurator,
            $spanSuppressionStrategy,
            $clock,
            $highResolutionTime,
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
    }

    public function build(?LoggerInterface $logger = null): TracerProviderInterface {
        $tracerProvider = $this->buildBase($logger);
        $this->copyStateInto($tracerProvider, new Context());

        return $tracerProvider;
    }
}
