<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\SpanProcessor;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OtelSDK\Trace\ReadableSpan;
use Nevay\OtelSDK\Trace\ReadWriteSpan;
use Nevay\OtelSDK\Trace\SpanProcessor;
use OpenTelemetry\Context\ContextInterface;
use function Amp\async;

final class MultiSpanProcessor implements SpanProcessor {

    private readonly iterable $spanProcessors;

    /**
     * @param iterable<mixed, SpanProcessor> $spanProcessors
     */
    private function __construct(iterable $spanProcessors) {
        $this->spanProcessors = $spanProcessors;
    }

    public static function composite(SpanProcessor ...$spanProcessors): SpanProcessor {
        return match (count($spanProcessors)) {
            0 => new NoopSpanProcessor(),
            1 => $spanProcessors[array_key_first($spanProcessors)],
            default => new MultiSpanProcessor($spanProcessors),
        };
    }

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        foreach ($this->spanProcessors as $spanProcessor) {
            $spanProcessor->onStart($span, $parentContext);
        }
    }

    public function onEnd(ReadableSpan $span): void {
        foreach ($this->spanProcessors as $spanProcessor) {
            $spanProcessor->onEnd($span);
        }
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        $futures = [];
        $shutdown = static function(SpanProcessor $p, ?Cancellation $cancellation): bool {
            return $p->shutdown($cancellation);
        };
        foreach ($this->spanProcessors as $spanProcessor) {
            $futures[] = async($shutdown, $spanProcessor, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        $futures = [];
        $forceFlush = static function(SpanProcessor $p, ?Cancellation $cancellation): bool {
            return $p->forceFlush($cancellation);
        };
        foreach ($this->spanProcessors as $spanProcessor) {
            $futures[] = async($forceFlush, $spanProcessor, $cancellation);
        }

        $success = true;
        foreach (Future::iterate($futures) as $future) {
            if (!$future->await()) {
                $success = false;
            }
        }

        return $success;
    }
}
