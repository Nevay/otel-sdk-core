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
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class SelfDiagnosticsSpanProcessor implements SpanProcessor {

    private readonly CounterInterface $createdCount;
    private readonly UpDownCounterInterface $liveCount;
    private readonly CounterInterface $endedCount;

    public function __construct(MeterProviderInterface $meterProvider) {
        $meter = $meterProvider->getMeter(
            'com.tobiasbachert.otel.sdk.trace',
            InstalledVersions::getVersionRanges('tbachert/otel-sdk-trace'),
        );

        $this->createdCount = $meter->createCounter(
            'otel.sdk.span.created_count',
            '{span}',
            'The number of spans which have been created',
        );
        $this->liveCount = $meter->createUpDownCounter(
            'otel.sdk.span.live_count',
            '{span}',
            'The number of created spans for which the end operation has not been called yet',
        );
        $this->endedCount = $meter->createCounter(
            'otel.sdk.span.ended_count',
            '{span}',
            'The number of spans which have been ended',
        );
    }

    public function onStart(ReadWriteSpan $span, ContextInterface $parentContext): void {
        $attributes = ['otel.span.is_sampled' => $span->getContext()->isSampled()];

        $this->createdCount->add(1, $attributes, $parentContext);
        $this->liveCount->add(1, $attributes, $parentContext);
    }

    public function onEnding(ReadWriteSpan $span): void {
        // no-op
    }

    public function onEnd(ReadableSpan $span): void {
        $attributes = ['otel.span.is_sampled' => $span->getContext()->isSampled()];

        $this->liveCount->add(-1, $attributes);
        $this->endedCount->add(1, $attributes);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
