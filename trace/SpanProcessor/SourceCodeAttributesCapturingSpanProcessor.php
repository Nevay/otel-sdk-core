<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanProcessor;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\StackTrace;
use Nevay\OTelSDK\Trace\Internal\SpanBuilder;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function count;
use function debug_backtrace;
use function is_subclass_of;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Adds source `code.*` attributes to spans.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/general/attributes/#source-code-attributes
 * @see https://opentelemetry.io/docs/specs/semconv/code/#attributes
 *
 * @experimental
 */
final class SourceCodeAttributesCapturingSpanProcessor implements SpanProcessor {

    /** @var int depth of the startSpan frame, expected to be 3 (this+multi+startSpan) */
    private int $scanDepth = 0;

    public function __construct(
        private readonly bool $captureStacktrace = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    private static function locateStartSpanFrame(array $trace, int $offset): ?int {
        while (--$offset >= 0) {
            $frame = $trace[$offset];
            $function = $frame['function'];
            $class = $frame['class'] ?? null;

            if ($function !== 'startSpan') {
                continue;
            }
            if ($class !== SpanBuilder::class && !is_subclass_of($class, SpanBuilderInterface::class)) {
                continue;
            }

            return $offset;
        }

        return null;
    }

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        $attributes = $span->getAttributes();
        $captureFilePath = !$attributes->has('code.file.path');
        $captureLineNumber = !$attributes->has('code.line.number');
        $captureFunctionName = !$attributes->has('code.function.name');
        $captureStacktrace = $this->captureStacktrace && !$attributes->has('code.stacktrace');

        if (!$captureFilePath && !$captureLineNumber && !$captureFunctionName && !$captureStacktrace) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $captureStacktrace ? 0 : $this->scanDepth + 1);
        if (($offset = self::locateStartSpanFrame($trace, $this->scanDepth)) === null) {
            if (!$captureStacktrace) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            if (($offset = self::locateStartSpanFrame($trace, count($trace))) === null) {
                $this->logger->warning('Failed to locate SpanBuilder::startSpan() frame for span "{span_name}"', [
                    'span_name' => $span->getName(),
                    'trace_id' => $span->getContext()->getTraceId(),
                    'span_id' => $span->getContext()->getSpanId(),
                    'stacktrace' => $trace,
                ]);
                return;
            }

            $this->scanDepth = $offset + 1;
            $this->logger->debug('Adjusted scan depth to located SpanBuilder::startSpan() frame at depth {depth}', [
                'depth' => $this->scanDepth,
            ]);
        }

        $file = $trace[$offset]['file'];
        $line = $trace[$offset]['line'];
        $function = $trace[$offset + 1]['function'] ?? '{main}';
        if (($class = $trace[$offset + 1]['class'] ?? null) !== null) {
            if (($pos = strpos($class, '@anonymous')) !== false) {
                $class = substr($class, 0, $pos + strlen('@anonymous'));
            }
            $function = $class . '::' . $function;
        }

        if ($captureFilePath) {
            $span->setAttribute('code.file.path', $file);
        }
        if ($captureLineNumber) {
            $span->setAttribute('code.line.number', $line);
        }
        if ($captureFunctionName) {
            $span->setAttribute('code.function.name', $function);
        }
        if ($captureStacktrace) {
            $span->setAttribute('code.stacktrace', StackTrace::formatBacktrace($trace, $offset + 1, StackTrace::DOT_SEPARATOR));
        }
    }

    public function onEnding(ReadWriteSpan $span): void {
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
