<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Composer\InstalledVersions;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\API\Trace\SpanInterface;

/**
 * @internal
 */
final class SelfDiagnosticsSpanListener implements SpanListener {

    private readonly UpDownCounterInterface $liveCount;
    private readonly CounterInterface $endedCount;

    public function __construct(MeterProviderInterface $meterProvider) {
        $meter = $meterProvider->getMeter(
            'com.tobiasbachert.otel.sdk.trace',
            InstalledVersions::getVersionRanges('tbachert/otel-sdk-trace'),
            'https://opentelemetry.io/schemas/1.34.0',
        );

        $this->liveCount = $meter->createUpDownCounter(
            'otel.sdk.span.live',
            '{span}',
            'The number of created spans for which the end operation has not been called yet',
        );
        $this->endedCount = $meter->createCounter(
            'otel.sdk.span.ended',
            '{span}',
            'The number of created spans for which the end operation was called',
        );
    }

    public function onStart(SpanInterface $span): void {
        $attributes = self::resolveAttributes($span);

        $this->liveCount->add(1, $attributes);
    }

    public function onEnding(SpanInterface $span): void {
        $attributes = self::resolveAttributes($span);

        $this->liveCount->add(-1, $attributes);
        $this->endedCount->add(1, $attributes);
    }

    private static function resolveAttributes(SpanInterface $span): iterable {
        return [
            'otel.span.sampling_result' => match (true) {
                $span->getContext()->isSampled() => 'RECORD_AND_SAMPLE',
                $span->isRecording() => 'RECORD_ONLY',
                default => 'DROP',
            },
        ];
    }
}
