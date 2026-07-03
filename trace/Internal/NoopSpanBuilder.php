<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\ContextResolver;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextStorageInterface;

/**
 * @internal
 */
final class NoopSpanBuilder implements SpanBuilderInterface {

    private readonly ?ContextStorageInterface $contextStorage;
    private ContextInterface|false|null $parent = null;

    public function __construct(?ContextStorageInterface $contextStorage) {
        $this->contextStorage = $contextStorage;
    }

    public function setParent(ContextInterface|false|null $context): SpanBuilderInterface {
        $this->parent = $context;

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface {
        return $this;
    }

    public function setAttribute(string $key, mixed $value): SpanBuilderInterface {
        return $this;
    }

    public function setAttributes(iterable $attributes): SpanBuilderInterface {
        return $this;
    }

    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface {
        return $this;
    }

    public function setSpanKind(int $spanKind): SpanBuilderInterface {
        return $this;
    }

    public function startSpan(): SpanInterface {
        $parent = ContextResolver::resolve($this->parent, $this->contextStorage);
        $parentSpan = Span::fromContext($parent);

        return $parentSpan->isRecording()
            ? Span::wrap($parentSpan->getContext())
            : $parentSpan;
    }
}
