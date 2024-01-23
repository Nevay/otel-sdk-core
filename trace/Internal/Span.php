<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\ClockAware;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\StackTrace;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\Span\Event;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\Span\Link;
use Nevay\OTelSDK\Trace\Span\Status;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Throwable;
use function count;

/**
 * @internal
 */
final class Span extends \OpenTelemetry\API\Trace\Span implements ReadWriteSpan, ClockAware {

    private readonly TracerState $tracerState;
    private readonly InstrumentationScope $instrumentationScope;
    private readonly Clock $clock;

    private string $name;
    private readonly SpanContextInterface $spanContext;
    private readonly Kind $spanKind;
    private readonly ?SpanContextInterface $parentContext;
    private AttributesBuilder $attributesBuilder;
    /** @var list<Link> */
    private array $links;
    private int $droppedLinksCount;
    /** @var list<Event> */
    private array $events = [];
    private int $droppedEventsCount = 0;
    private Status $status = Status::Unset;
    private ?string $statusDescription = null;
    private readonly int $startTimestamp;
    private ?int $endTimestamp = null;

    /**
     * @param list<Link> $links
     */
    public function __construct(
        TracerState $tracerState,
        InstrumentationScope $instrumentationScope,
        Clock $clock,
        string $name,
        SpanContextInterface $spanContext,
        Kind $spanKind,
        ?SpanContextInterface $parentContext,
        array $links,
        int $droppedLinksCount,
        AttributesBuilder $attributes,
        int $startTimestamp,
    ) {
        $this->tracerState = $tracerState;
        $this->instrumentationScope = $instrumentationScope;
        $this->clock = $clock;
        $this->name = $name;
        $this->spanContext = $spanContext;
        $this->spanKind = $spanKind;
        $this->parentContext = $parentContext;
        $this->links = $links;
        $this->droppedLinksCount = $droppedLinksCount;
        $this->attributesBuilder = $attributes;
        $this->startTimestamp = $startTimestamp;
    }

    public function __clone() {
        $this->attributesBuilder = clone $this->attributesBuilder;
    }

    public function getClock(): Clock {
        return $this->clock;
    }

    public function getInstrumentationScope(): InstrumentationScope {
        return $this->instrumentationScope;
    }

    public function getResource(): Resource {
        return $this->tracerState->resource;
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
        return $this->endTimestamp ?? $this->clock->now();
    }

    public function isRecording(): bool {
        return $this->endTimestamp === null;
    }

    public function setAttribute(string $key, mixed $value): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }

        $this->attributesBuilder->add($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }

        $this->attributesBuilder->addAll($attributes);

        return $this;
    }

    /**
     * @experimental
     */
    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }
        if (!$context->isValid()) {
            return $this;
        }
        if ($this->tracerState->linkCountLimit === count($this->links)) {
            $this->droppedLinksCount++;
            return $this;
        }

        $linkAttributes = $this->tracerState->linkAttributesFactory
            ->builder()
            ->addAll($attributes)
            ->build();

        $this->links[] = new Link($context, $linkAttributes);

        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }
        if ($this->tracerState->eventCountLimit === count($this->events)) {
            $this->droppedEventsCount++;
            return $this;
        }

        $timestamp ??= $this->clock->now();
        $eventAttributes = $this->tracerState->eventAttributesFactory
            ->builder()
            ->addAll($attributes)
            ->build();

        $this->events[] = new Event($name, $eventAttributes, $timestamp);

        return $this;
    }

    public function recordException(Throwable $exception, iterable $attributes = [], ?int $timestamp = null): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }
        if ($this->tracerState->eventCountLimit === count($this->events)) {
            $this->droppedEventsCount++;
            return $this;
        }

        $timestamp ??= $this->clock->now();
        $eventAttributes = $this->tracerState->eventAttributesFactory
            ->builder()
            ->add('exception.type', $exception::class)
            ->add('exception.message', $exception->getMessage())
            ->add('exception.stacktrace', StackTrace::format($exception, StackTrace::DOT_SEPARATOR))
            ->addAll($attributes)
            ->build();

        $this->events[] = new Event('exception', $eventAttributes, $timestamp);

        return $this;
    }

    public function updateName(string $name): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }

        $this->name = $name;

        return $this;
    }

    public function setStatus(string $code, ?string $description = null): SpanInterface {
        if (!$this->isRecording()) {
            return $this;
        }

        $status = Status::fromApi($code);

        if ($status->compareTo($this->status) < 0) {
            return $this;
        }

        $this->status = $status;
        $this->statusDescription = $status->allowsDescription()
            ? $description
            : null;

        return $this;
    }

    public function end(?int $endEpochNanos = null): void {
        if (!$this->isRecording()) {
            return;
        }

        $this->endTimestamp = $endEpochNanos ?? $this->clock->now();
        $this->tracerState->spanProcessor->onEnd($this);
    }
}
