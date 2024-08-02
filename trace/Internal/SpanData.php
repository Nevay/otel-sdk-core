<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\Span\Event;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\Span\Link;
use Nevay\OTelSDK\Trace\Span\Status;
use OpenTelemetry\API\Trace\SpanContextInterface;
use function assert;

/**
 * @internal
 */
final class SpanData implements ReadableSpan {

    public readonly Resource $resource;
    public readonly InstrumentationScope $instrumentationScope;

    public string $name;
    public readonly SpanContextInterface $spanContext;
    public readonly Kind $spanKind;
    public readonly ?SpanContextInterface $parentContext;
    public AttributesBuilder $attributesBuilder;
    /** @var list<Link> */
    public array $links;
    public int $droppedLinksCount;
    /** @var list<Event> */
    public array $events = [];
    public int $droppedEventsCount = 0;
    public Status $status = Status::Unset;
    public ?string $statusDescription = null;
    public readonly int $startTimestamp;
    public ?int $endTimestamp = null;

    /**
     * @param list<Link> $links
     */
    public function __construct(
        Resource $resource,
        InstrumentationScope $instrumentationScope,
        string $name,
        SpanContextInterface $spanContext,
        Kind $spanKind,
        ?SpanContextInterface $parentContext,
        array $links,
        int $droppedLinksCount,
        AttributesBuilder $attributes,
        int $startTimestamp,
    ) {
        $this->resource = $resource;
        $this->instrumentationScope = $instrumentationScope;
        $this->name = $name;
        $this->spanContext = $spanContext;
        $this->spanKind = $spanKind;
        $this->parentContext = $parentContext;
        $this->links = $links;
        $this->droppedLinksCount = $droppedLinksCount;
        $this->attributesBuilder = $attributes;
        $this->startTimestamp = $startTimestamp;
    }

    public function getInstrumentationScope(): InstrumentationScope {
        return $this->instrumentationScope;
    }

    public function getResource(): Resource {
        return $this->resource;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getContext(): SpanContextInterface {
        return $this->spanContext;
    }

    public function getSpanKind(): Kind {
        return $this->spanKind;
    }

    public function getParentContext(): ?SpanContextInterface {
        return $this->parentContext;
    }

    public function getAttributes(): Attributes {
        return $this->attributesBuilder->build();
    }

    public function getLinks(): iterable {
        return $this->links;
    }

    public function getDroppedLinksCount(): int {
        return $this->droppedLinksCount;
    }

    public function getEvents(): iterable {
        return $this->events;
    }

    public function getDroppedEventsCount(): int {
        return $this->droppedEventsCount;
    }

    public function getStatus(): Status {
        return $this->status;
    }

    public function getStatusDescription(): ?string {
        return $this->statusDescription;
    }

    public function getStartTimestamp(): int {
        return $this->startTimestamp;
    }

    public function getEndTimestamp(): int {
        assert($this->endTimestamp !== null);
        return $this->endTimestamp;
    }

    public function isRecording(): bool {
        return $this->endTimestamp === null;
    }
}
