<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

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
use Nevay\OTelSDK\Trace\Internal\TracerProvider;
use Nevay\OTelSDK\Trace\Sampler\AlwaysOnSampler;
use Nevay\OTelSDK\Trace\Sampler\ParentBasedSampler;
use Nevay\OTelSDK\Trace\SpanProcessor\MultiSpanProcessor;
use Nevay\OTelSDK\Trace\SpanProcessor\NoopSpanProcessor;
use Nevay\OTelSDK\Trace\SpanSuppression\NoopSuppressionStrategy;
use OpenTelemetry\API\Configuration\Context;
use Psr\Log\LoggerInterface;

final class TracerProviderBuilder {

    private ?Resource $resource = null;
    /** @var list<SpanProcessor> */
    private array $spanProcessors = [];

    private ?IdGenerator $idGenerator = null;
    private ?Sampler $sampler = null;
    /** @var Configurator<TracerConfig>|null */
    private ?Configurator $configurator = null;
    private ?SpanSuppressionStrategy $spanSuppressionStrategy = null;

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

    public function setResource(Resource $resource): self {
        $this->resource = $resource;

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
     * @param Configurator<TracerConfig> $configurator
     *
     * @experimental
     */
    public function setTracerConfigurator(Configurator $configurator): self {
        $this->configurator = $configurator;

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
     * @internal
     */
    public function copyStateInto(TracerProvider $tracerProvider, Context $selfDiagnostics): void {
        $tracerProvider->configurator = $this->configurator ?? new Configurator\NoopConfigurator();

        $idGenerator = $this->idGenerator ?? new RandomIdGenerator();
        $sampler = $this->sampler ?? new ParentBasedSampler(new AlwaysOnSampler());
        $spanProcessors = $this->spanProcessors;
        if ($tracerProvider->tracerState->logger) {
            $spanProcessors[] = new LogDiscardedSpanProcessor($tracerProvider->tracerState->logger);
        }

        $tracerProvider->tracerState->spanListener = $spanProcessors[] = new SelfDiagnosticsSpanProcessor($selfDiagnostics->meterProvider);
        $tracerProvider->tracerState->resource = $this->resource ?? Resource::default();
        $tracerProvider->tracerState->idGenerator = $idGenerator;
        $tracerProvider->tracerState->sampler = $sampler;
        $tracerProvider->tracerState->spanProcessor = MultiSpanProcessor::composite(...$spanProcessors);

        $tracerProvider->tracerState->spanAttributesFactory = AttributesLimitingFactory::create(
            $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $tracerProvider->tracerState->eventAttributesFactory = AttributesLimitingFactory::create(
            $this->eventAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->eventAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $tracerProvider->tracerState->linkAttributesFactory = AttributesLimitingFactory::create(
            $this->linkAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->linkAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $tracerProvider->tracerState->eventCountLimit = $this->eventCountLimit ?? 128;
        $tracerProvider->tracerState->linkCountLimit = $this->linkCountLimit ?? 128;

        $tracerProvider->spanSuppressionStrategy = $this->spanSuppressionStrategy ?? new NoopSuppressionStrategy();

        $tracerProvider->reload();
    }

    /**
     * @internal
     */
    public static function buildBase(?LoggerInterface $logger = null, (Clock&HighResolutionTime)|null $clock = null): TracerProvider {
        return new TracerProvider(
            null,
            Resource::default(),
            UnlimitedAttributesFactory::create(),
            new Configurator\NoopConfigurator(),
            new NoopSuppressionStrategy(),
            $clock ?? SystemClock::create(),
            $clock ?? SystemHighResolutionTime::create(),
            new RandomIdGenerator(),
            new ParentBasedSampler(new AlwaysOnSampler()),
            new NoopSpanProcessor(),
            new NoopSpanListener(),
            AttributesLimitingFactory::create(),
            AttributesLimitingFactory::create(),
            AttributesLimitingFactory::create(),
            128,
            128,
            $logger,
        );
    }

    public function build(?LoggerInterface $logger = null): TracerProviderInterface {
        $tracerProvider = self::buildBase($logger);
        $this->copyStateInto($tracerProvider, new Context());

        return $tracerProvider;
    }
}
