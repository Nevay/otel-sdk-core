<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\SystemHighResolutionTime;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Trace\IdGenerator\RandomIdGenerator;
use Nevay\OTelSDK\Trace\Internal\LogDiscardedSpanProcessor;
use Nevay\OTelSDK\Trace\Internal\TracerProvider;
use Nevay\OTelSDK\Trace\Sampler\AlwaysOnSampler;
use Nevay\OTelSDK\Trace\Sampler\ParentBasedSampler;
use Nevay\OTelSDK\Trace\SpanProcessor\MultiSpanProcessor;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;

final class TracerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<SpanProcessor> */
    private array $spanProcessors = [];

    private ?IdGenerator $idGenerator = null;
    private ?Sampler $sampler = null;

    private ?Closure $tracerConfigurator = null;

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
    private bool $retainGeneralIdentityAttributes = false;

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
     * @param Closure(InstrumentationScope): TracerConfig $tracerConfigurator
     *
     * @experimental
     */
    public function setTracerConfigurator(Closure $tracerConfigurator): self {
        $this->tracerConfigurator = $tracerConfigurator;

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

    public function retainGeneralIdentityAttributes(bool $retainGeneralIdentityAttributes = true): self {
        $this->retainGeneralIdentityAttributes = $retainGeneralIdentityAttributes;

        return $this;
    }

    public function build(?LoggerInterface $logger = null): TracerProviderInterface&Provider {
        $idGenerator = $this->idGenerator ?? new RandomIdGenerator();
        $sampler = $this->sampler ?? new ParentBasedSampler(new AlwaysOnSampler());

        $spanAttributesFactory = AttributesLimitingFactory::create(
            $this->spanAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->spanAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
            !$this->retainGeneralIdentityAttributes
                ? AttributesLimitingFactory::rejectKeyFilter('enduser')
                : null,
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

        $spanProcessors = $this->spanProcessors;
        if ($logger) {
            $spanProcessors[] = new LogDiscardedSpanProcessor($logger);
        }

        $tracerConfigurator = $this->tracerConfigurator
            ?? static fn(InstrumentationScope $instrumentationScope): TracerConfig => new TracerConfig();

        return new TracerProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            $tracerConfigurator,
            SystemClock::create(),
            SystemHighResolutionTime::create(),
            $idGenerator,
            $sampler,
            MultiSpanProcessor::composite(...$spanProcessors),
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
    }
}
