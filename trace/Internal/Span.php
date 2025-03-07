<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\Attributes;
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

    public function __construct(
        private readonly TracerState $tracerState,
        private readonly Clock $clock,
        private readonly SpanData $spanData,
        private bool $recording = true,
    ) {}

    public function __clone() {
        $this->recording = false;
    }

    public function getClock(): Clock {
        return $this->clock;
    }

    public function getInstrumentationScope(): InstrumentationScope {
        return $this->spanData->instrumentationScope;
    }

    public function getResource(): Resource {
        return $this->spanData->resource;
    }

    public function getName(): string {
        return $this->spanData->name;
    }

    public function getContext(): SpanContextInterface {
        return $this->spanData->spanContext;
    }

    public function getSpanKind(): Kind {
        return $this->spanData->spanKind;
    }

    public function getParentContext(): ?SpanContextInterface {
        return $this->spanData->parentContext;
    }

    public function getAttributes(): Attributes {
        return $this->spanData->attributesBuilder->build();
    }

    public function getLinks(): iterable {
        return $this->spanData->links;
    }

    public function getDroppedLinksCount(): int {
        return $this->spanData->droppedLinksCount;
    }

    public function getEvents(): iterable {
        return $this->spanData->events;
    }

    public function getDroppedEventsCount(): int {
        return $this->spanData->droppedEventsCount;
    }

    public function getStatus(): Status {
        return $this->spanData->status;
    }

    public function getStatusDescription(): ?string {
        return $this->spanData->statusDescription;
    }

    public function getStartTimestamp(): int {
        return $this->spanData->startTimestamp;
    }

    public function getEndTimestamp(): int {
        return $this->spanData->endTimestamp ?? $this->clock->now();
    }

    public function isRecording(): bool {
        return $this->recording;
    }

    public function setAttribute(string $key, mixed $value): SpanInterface {
        if (!$this->recording) {
            return $this;
        }

        $this->spanData->attributesBuilder->add($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanInterface {
        if (!$this->recording) {
            return $this;
        }

        $this->spanData->attributesBuilder->addAll($attributes);

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface {
        if (!$this->recording) {
            return $this;
        }
        if ($this->tracerState->linkCountLimit === count($this->spanData->links)) {
            $this->spanData->droppedLinksCount++;
            return $this;
        }

        $linkAttributes = $this->tracerState->linkAttributesFactory
            ->builder()
            ->addAll($attributes)
            ->build();

        $this->spanData->links[] = new Link($context, $linkAttributes);

        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface {
        if (!$this->recording) {
            return $this;
        }
        if ($this->tracerState->eventCountLimit === count($this->spanData->events)) {
            $this->spanData->droppedEventsCount++;
            return $this;
        }

        $timestamp ??= $this->clock->now();
        $eventAttributes = $this->tracerState->eventAttributesFactory
            ->builder()
            ->addAll($attributes)
            ->build();

        $this->spanData->events[] = new Event($name, $eventAttributes, $timestamp);

        return $this;
    }

    public function recordException(Throwable $exception, iterable $attributes = [], ?int $timestamp = null): SpanInterface {
        if (!$this->recording) {
            return $this;
        }
        if ($this->tracerState->eventCountLimit === count($this->spanData->events)) {
            $this->spanData->droppedEventsCount++;
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

        $this->spanData->events[] = new Event('exception', $eventAttributes, $timestamp);

        return $this;
    }

    public function updateName(string $name): SpanInterface {
        if (!$this->recording) {
            return $this;
        }

        $this->spanData->name = $name;

        return $this;
    }

    public function setStatus(string $code, ?string $description = null): SpanInterface {
        if (!$this->recording) {
            return $this;
        }

        $status = Status::fromApi($code);

        if ($status->compareTo($this->spanData->status) < 0) {
            return $this;
        }

        $this->spanData->status = $status;
        $this->spanData->statusDescription = $status->allowsDescription()
            ? $description
            : null;

        return $this;
    }

    public function end(?int $endEpochNanos = null): void {
        if (!$this->recording) {
            return;
        }
        if ($this->spanData->endTimestamp !== null) {
            return;
        }

        $this->recording = false;
        $this->spanData->endTimestamp = $endEpochNanos ?? $this->clock->now();

        /*
         * The SDK MUST guarantee that the span can no longer be modified by any
         * other thread before invoking OnEnding of the first SpanProcessor.
         * From that point on, modifications are only allowed synchronously from
         * within the invoked OnEnding callbacks.
         */
        $span = new Span($this->tracerState, $this->clock, $this->spanData);
        $this->tracerState->spanProcessor->onEnding($span);
        $span->recording = false;

        $this->tracerState->spanProcessor->onEnd($this->spanData);
    }
}
