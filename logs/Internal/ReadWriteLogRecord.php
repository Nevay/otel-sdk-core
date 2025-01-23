<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * @internal
 */
final class ReadWriteLogRecord implements \Nevay\OTelSDK\Logs\ReadWriteLogRecord {

    public function __construct(
        private readonly InstrumentationScope $instrumentationScope,
        private readonly Resource $resource,
        private AttributesBuilder $attributesBuilder,
        private ?int $timestamp = null,
        private ?int $observedTimestamp = null,
        private ?SpanContextInterface $spanContext = null,
        private ?string $severityText = null,
        private ?int $severityNumber = null,
        private mixed $body = null,
        private ?string $eventName = null,
    ) {}

    public function __clone() {
        $this->attributesBuilder = clone $this->attributesBuilder;
    }

    public function getInstrumentationScope(): InstrumentationScope {
        return $this->instrumentationScope;
    }

    public function getResource(): Resource {
        return $this->resource;
    }

    public function getTimestamp(): ?int {
        return $this->timestamp;
    }

    public function getObservedTimestamp(): ?int {
        return $this->observedTimestamp;
    }

    public function getSpanContext(): ?SpanContextInterface {
        return $this->spanContext;
    }

    public function getSeverityText(): ?string {
        return $this->severityText;
    }

    public function getSeverityNumber(): ?int {
        return $this->severityNumber;
    }

    public function getBody(): mixed {
        return $this->body;
    }

    public function getAttributes(): Attributes {
        return $this->attributesBuilder->build();
    }

    public function getEventName(): ?string {
        return $this->eventName;
    }

    public function setTimestamp(?int $timestamp): self {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function setObservedTimestamp(?int $observedTimestamp): self {
        $this->observedTimestamp = $observedTimestamp;

        return $this;
    }

    public function setSpanContext(?SpanContextInterface $spanContext): self {
        $this->spanContext = $spanContext;

        return $this;
    }

    public function setSeverityText(?string $severityText): self {
        $this->severityText = $severityText;

        return $this;
    }

    public function setSeverityNumber(?int $severityNumber): self {
        $this->severityNumber = $severityNumber;

        return $this;
    }

    public function setBody(mixed $body): self {
        $this->body = $body;

        return $this;
    }

    public function setAttribute(string $key, mixed $value): self {
        $this->attributesBuilder->add($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): self {
        $this->attributesBuilder->addAll($attributes);

        return $this;
    }

    public function setEventName(?string $eventName): self {
        $this->eventName = $eventName;

        return $this;
    }
}
