<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * @internal
 */
final class NoopSpanListener implements SpanListener {

    public function onStartNonRecording(?SpanContextInterface $parent): void {
        // no-op
    }
}
