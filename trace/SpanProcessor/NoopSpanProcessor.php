<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\SpanProcessor;

use Amp\Cancellation;
use Nevay\OtelSDK\Trace\ReadableSpan;
use Nevay\OtelSDK\Trace\ReadWriteSpan;
use Nevay\OtelSDK\Trace\SpanProcessor;
use OpenTelemetry\Context\ContextInterface;

final class NoopSpanProcessor implements SpanProcessor {

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        // no-op
    }

    public function onEnd(ReadableSpan $span): void {
        // no-op
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
