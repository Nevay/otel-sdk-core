<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\ClockAware;
use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\MonotonicClock;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\Span\Link;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use function assert;
use function count;

/**
 * @internal
 */
final class SpanBuilder implements SpanBuilderInterface {

    private readonly TracerState $tracerState;
    private readonly InstrumentationScope $instrumentationScope;

    private readonly string $name;
    private ContextInterface|false|null $parent = null;
    private AttributesBuilder $attributesBuilder;
    /** @var list<Link> */
    private array $links = [];
    private int $droppedLinksCount = 0;
    private Kind $spanKind = Kind::Internal;
    private ?int $startTimestamp = null;

    public function __construct(
        TracerState $tracerState,
        InstrumentationScope $instrumentationScope,
        string $name,
    ) {
        $this->tracerState = $tracerState;
        $this->instrumentationScope = $instrumentationScope;
        $this->name = $name;

        $this->attributesBuilder = $tracerState->spanAttributesFactory->builder();
    }

    public function __clone() {
        $this->attributesBuilder = clone $this->attributesBuilder;
    }

    public function setParent($context): SpanBuilderInterface {
        $this->parent = $context;

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface {
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

    public function setAttribute(string $key, mixed $value): SpanBuilderInterface {
        $this->attributesBuilder->add($key, $value);

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanBuilderInterface {
        $this->attributesBuilder->addAll($attributes);

        return $this;
    }

    public function setSpanKind(int $spanKind): SpanBuilderInterface {
        $this->spanKind = Kind::fromApi($spanKind);

        return $this;
    }

    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface {
        $this->startTimestamp = $timestampNanos;

        return $this;
    }

    public function startSpan(): SpanInterface {
        $parent = ContextResolver::resolve($this->parent, $this->tracerState->contextStorage);
        $parentSpan = Span::fromContext($parent);

        $parentSpanContext = $parentSpan->getContext()->isValid()
            ? $parentSpan->getContext()
            : null;

        $traceId = $parentSpanContext?->getTraceIdBinary()
            ?? $this->tracerState->idGenerator->generateTraceIdBinary();
        $flags = $parentSpanContext?->getTraceFlags()
            ?? $this->tracerState->idGenerator->traceFlags();
        $flags &= 0x2;

        $name = $this->name;
        $spanKind = $this->spanKind;
        $attributesBuilder = clone $this->attributesBuilder;
        $links = $this->links;
        $droppedLinksCount = $this->droppedLinksCount;
        $startTimestamp = $this->startTimestamp;

        $samplingResult = $this->tracerState->sampler->shouldSample(
            $parent,
            $traceId,
            $name,
            $spanKind,
            $attributesBuilder->build(),
            $links,
        );
        $traceState = $samplingResult->traceState()
            ?? $parentSpanContext?->getTraceState();

        $spanId = $this->tracerState->idGenerator->generateSpanIdBinary();
        $flags |= $samplingResult->traceFlags();
        $spanContext = new SpanContext($traceId, $spanId, $flags, $traceState);
        assert(SpanContextValidator::isValidTraceId($spanContext->getTraceId()));
        assert(SpanContextValidator::isValidSpanId($spanContext->getSpanId()));

        if (!$samplingResult->shouldRecord()) {
            assert(!$spanContext->isSampled());
            return Span::wrap($spanContext);
        }

        // Use monotonic clock within recorded traces
        $clock = $parentSpan instanceof ClockAware
            ? $parentSpan->getClock()
            : $this->tracerState->clock;
        $clock = MonotonicClock::anchor($clock, $this->tracerState->highResolutionTime);
        $startTimestamp ??= $clock->now();

        $attributesBuilder->addAll($samplingResult->additionalAttributes());

        $span = new Span(
            $this->tracerState,
            $this->instrumentationScope,
            $clock,
            $name,
            $spanContext,
            $spanKind,
            $parentSpanContext,
            $links,
            $droppedLinksCount,
            $attributesBuilder,
            $startTimestamp,
        );

        $this->tracerState->spanProcessor->onStart($span, $parent);

        return $span;
    }
}
