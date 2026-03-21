<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Trace\IdGenerator\RandomIdGenerator;
use Nevay\OTelSDK\Trace\Sampler\AlwaysOnSampler;
use Nevay\OTelSDK\Trace\Sampler\ParentBasedSampler;
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

    public function build(LoggerInterface|Context|null $selfDiagnostics = null, TracerProvider $tracerProvider = new TracerProvider()): TracerProviderInterface {
        if ($selfDiagnostics instanceof LoggerInterface) {
            $selfDiagnostics = new Context(logger: $selfDiagnostics);
        }
        if ($selfDiagnostics) {
            $tracerProvider->initSelfDiagnostics($selfDiagnostics);
        }

        $tracerProvider->update(function(TracerState $state): void {
            $state->configurator = $this->configurator ?? new Configurator\NoopConfigurator();
            $state->idGenerator = $this->idGenerator ?? new RandomIdGenerator();
            $state->sampler = $this->sampler ?? new ParentBasedSampler(new AlwaysOnSampler());
            $state->spanProcessors = $this->spanProcessors;
            $state->resource = $this->resource ?? Resource::default();
            $state->spanAttributesFactory = AttributesLimitingFactory::create(
                $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
                $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
            );
            $state->eventAttributesFactory = AttributesLimitingFactory::create(
                $this->eventAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
                $this->eventAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
            );
            $state->linkAttributesFactory = AttributesLimitingFactory::create(
                $this->linkAttributeCountLimit ?? $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
                $this->linkAttributeValueLengthLimit ?? $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
            );
            $state->eventCountLimit = $this->eventCountLimit ?? 128;
            $state->linkCountLimit = $this->linkCountLimit ?? 128;
            $state->spanSuppressionStrategy = $this->spanSuppressionStrategy ?? new NoopSuppressionStrategy();
        });

        return $tracerProvider;
    }
}
