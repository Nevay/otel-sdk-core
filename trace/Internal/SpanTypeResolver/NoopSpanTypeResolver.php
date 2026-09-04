<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanTypeResolver;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;
use Nevay\OTelSDK\Trace\SpanTypeResolver;

/**
 * @internal
 */
final class NoopSpanTypeResolver implements SpanTypeResolver {

    public function resolveSpanType(string $spanName, Kind $spanKind, Attributes $attributes): ?string {
        return null;
    }
}
