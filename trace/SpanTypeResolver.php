<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;

/**
 * @experimental
 */
interface SpanTypeResolver {

    public function resolveSpanType(string $spanName, Kind $spanKind, Attributes $attributes): ?string;
}
