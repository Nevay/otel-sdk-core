<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Amp\Cancellation;
use Composer\InstalledVersions;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\ReadWriteSpan;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use function assert;

/**
 * @internal
 */
final class SelfDiagnosticsSpanProcessor implements SpanProcessor, SpanListener {

    private readonly UpDownCounterInterface $liveCount;
    private readonly CounterInterface $startedCount;

    public function __construct(MeterProviderInterface $meterProvider) {
        $meter = $meterProvider->getMeter(
            'com.tobiasbachert.otel.sdk.trace',
            InstalledVersions::getPrettyVersion('tbachert/otel-sdk-trace'),
            'https://opentelemetry.io/schemas/1.36.0',
        );

        $this->liveCount = $meter->createUpDownCounter(
            'otel.sdk.span.live',
            '{span}',
            'The number of created spans with recording=true for which the end operation has not been called yet',
        );
        $this->startedCount = $meter->createCounter(
            'otel.sdk.span.started',
            '{span}',
            'The number of created spans',
        );
    }

    public function onStartNonRecording(?SpanContextInterface $parent): void {
        $samplingResult = ['otel.span.sampling_result' => 'DROP'];
        $parentOrigin = self::resolveParentOrigin($parent);

        $this->startedCount->add(1, $samplingResult + $parentOrigin);
    }

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        $samplingResult = self::resolveSamplingResult($span);
        $parentOrigin = self::resolveParentOrigin($span->getParentContext());

        $this->startedCount->add(1, $samplingResult + $parentOrigin);
        $this->liveCount->add(1, $samplingResult);
    }

    public function onEnding(SpanInterface $span): void {
        $samplingResult = self::resolveSamplingResult($span);

        $this->liveCount->add(-1, $samplingResult);
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

    private static function resolveSamplingResult(SpanInterface $span): array {
        assert($span->isRecording());
        return $span->getContext()->isSampled()
            ? ['otel.span.sampling_result' => 'RECORD_AND_SAMPLE']
            : ['otel.span.sampling_result' => 'RECORD_ONLY'];
    }

    private static function resolveParentOrigin(?SpanContextInterface $parent): array {
        return match (true) {
            !$parent?->isValid() => ['otel.span.parent.origin' => 'none'],
            !$parent->isRemote() => ['otel.span.parent.origin' => 'local'],
            $parent->isRemote()  => ['otel.span.parent.origin' => 'remote'],
        };
    }
}
