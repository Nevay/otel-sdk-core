<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Throwable;

final class NonRecordingSpan extends \OpenTelemetry\API\Trace\Span implements SpanInterface {

    public function __construct(
        private readonly SpanContextInterface $spanContext,
    ) {}

    public function getContext(): SpanContextInterface {
        return $this->spanContext;
    }

    public function isRecording(): bool {
        return false;
    }

    public function setAttribute(string $key, float|array|bool|int|string|null $value): SpanInterface {
        return $this;
    }

    public function setAttributes(iterable $attributes): SpanInterface {
        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface {
        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface {
        return $this;
    }

    public function recordException(Throwable $exception, iterable $attributes = []): SpanInterface {
        return $this;
    }

    public function updateName(string $name): SpanInterface {
        return $this;
    }

    public function setStatus(string $code, ?string $description = null): SpanInterface {
        return $this;
    }

    public function end(?int $endEpochNanos = null): void {
        // no-op
    }
}
