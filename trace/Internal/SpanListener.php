<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use OpenTelemetry\API\Trace\SpanInterface;

/**
 * @internal
 */
interface SpanListener {

    public function onStart(SpanInterface $span): void;

    public function onEnding(SpanInterface $span): void;
}
