<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace;

use Nevay\OtelSDK\Common\AttributesLimitingFactory;
use Nevay\OtelSDK\Common\Provider;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Common\SystemClock;
use Nevay\OtelSDK\Common\SystemHighResolutionTime;
use Nevay\OtelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OtelSDK\Trace\IdGenerator\RandomIdGenerator;
use Nevay\OtelSDK\Trace\Internal\TracerProvider;
use Nevay\OtelSDK\Trace\Sampler\AlwaysOnSampler;
use Nevay\OtelSDK\Trace\Sampler\ParentBasedSampler;
use Nevay\OtelSDK\Trace\SpanProcessor\MultiSpanProcessor;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;

final class TracerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<SpanProcessor> */
    private array $spanProcessors = [];

    private ?IdGenerator $idGenerator = null;
    private ?Sampler $sampler = null;

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

    public function setIdGenerator(IdGenerator $idGenerator): self {
        $this->idGenerator = $idGenerator;

        return $this;
    }

    public function setSampler(Sampler $sampler): self {
        $this->sampler = $sampler;

        return $this;
    }

    public function setAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->attributeCountLimit = $attributeCountLimit;
        $this->attributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setSpanAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->spanAttributeCountLimit = $attributeCountLimit;
        $this->spanAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setEventAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->eventAttributeCountLimit = $attributeCountLimit;
        $this->eventAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setLinkAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->linkAttributeCountLimit = $attributeCountLimit;
        $this->linkAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setEventCountLimit(int $eventCountLimit): self {
        $this->eventCountLimit = $eventCountLimit;

        return $this;
    }

    public function setLinkCountLimit(int $linkCountLimit): self {
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
            $this->eventAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->eventAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $linkAttributesFactory = AttributesLimitingFactory::create(
            $this->linkAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->linkAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );
        $eventCountLimit = $this->eventCountLimit ?? 128;
        $linkCountLimit = $this->linkCountLimit ?? 128;

        return new TracerProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            SystemClock::create(),
            SystemHighResolutionTime::create(),
            $idGenerator,
            $sampler,
            MultiSpanProcessor::composite(...$this->spanProcessors),
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
    }
}
