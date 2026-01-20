<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\AttributesBuilder;
use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Common\MonotonicClock;
use Nevay\OTelSDK\Trace\SamplingParams;
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

    private readonly Tracer $tracer;

    private readonly string $name;
    private ContextInterface|false|null $parent = null;
    private AttributesBuilder $attributesBuilder;
    /** @var list<Link> */
    private array $links = [];
    private int $droppedLinksCount = 0;
    private Kind $spanKind = Kind::Internal;
    private ?int $startTimestamp = null;

    public function __construct(
        Tracer $tracer,
        string $name,
    ) {
        $this->tracer = $tracer;
        $this->name = $name;

        $this->attributesBuilder = $tracer->tracerState->spanAttributesFactory->builder();
    }

    public function __clone() {
        $this->attributesBuilder = clone $this->attributesBuilder;
    }

    public function setParent($context): SpanBuilderInterface {
        $this->parent = $context;

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface {
        if ($this->tracer->tracerState->linkCountLimit >= count($this->links)) {
            $this->droppedLinksCount++;
            return $this;
        }

        $linkAttributes = $this->tracer->tracerState->linkAttributesFactory
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
        $tracerState = $this->tracer->tracerState;
        $parent = ContextResolver::resolve($this->parent, $tracerState->contextStorage);
        $parentSpan = Span::fromContext($parent);

        if (!$this->tracer->enabled) {
            return $parentSpan->isRecording()
                ? Span::wrap($parentSpan->getContext())
                : $parentSpan;
        }

        $name = $this->name;
        $spanKind = $this->spanKind;
        $attributesBuilder = clone $this->attributesBuilder;
        $links = $this->links;
        $droppedLinksCount = $this->droppedLinksCount;
        $startTimestamp = $this->startTimestamp;

        $parentSpanContext = $parentSpan->getContext()->isValid()
            ? $parentSpan->getContext()
            : null;

        $traceId = $parentSpanContext?->getTraceIdBinary()
            ?? $tracerState->idGenerator->generateTraceIdBinary();
        $flags = $parentSpanContext?->getTraceFlags()
            ?? $tracerState->idGenerator->traceFlags();
        $flags &= 0x2;

        $samplingParams = new SamplingParams(
            $parent,
            $parentSpan->getContext(),
            $traceId,
            $flags,
            $name,
            $spanKind,
            $attributesBuilder->build(),
            $links,
        );

        $spanSuppression = $this->tracer->spanSuppressor->resolveSuppression($samplingParams);
        if ($spanSuppression->isSuppressed($parent)) {
            return $parentSpan->isRecording()
                ? Span::wrap($parentSpan->getContext())
                : $parentSpan;
        }

        $samplingResult = $tracerState->sampler->shouldSample($samplingParams);
        $traceState = $samplingResult->traceState()
            ?? $parentSpanContext?->getTraceState();

        $spanId = $tracerState->idGenerator->generateSpanIdBinary();
        $flags |= $samplingResult->traceFlags();
        $spanContext = new SpanContext($traceId, $spanId, $flags, $traceState);
        assert(SpanContextValidator::isValidTraceId($spanContext->getTraceId()));
        assert(SpanContextValidator::isValidSpanId($spanContext->getSpanId()));

        if (!$samplingResult->shouldRecord() && assert(!$spanContext->isSampled())) {
            $tracerState->spanListener->onStartNonRecording($parentSpanContext);

            return new NonRecordingSpan($spanContext, $spanSuppression);
        }

        // Use monotonic clock within recorded traces
        $clock = $parentSpan instanceof Span
            ? $parentSpan->clock
            : $tracerState->clock;
        $clock = MonotonicClock::anchor($clock, $tracerState->highResolutionTime);
        $startTimestamp ??= $clock->now();

        $attributesBuilder->addAll($samplingResult->additionalAttributes());

        $span = new Span(
            $tracerState,
            $clock,
            new SpanData(
                $tracerState->resource,
                $this->tracer->instrumentationScope,
                $name,
                $spanContext,
                $spanKind,
                $parentSpanContext,
                $links,
                $droppedLinksCount,
                $attributesBuilder,
                $startTimestamp,
            ),
            $spanSuppression,
        );

        $tracerState->spanProcessor->onStart($span, $parent);

        return $span;
    }
}
