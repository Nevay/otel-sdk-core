<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Span;

use Nevay\OtelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

final class Link {

    public function __construct(
        public readonly SpanContextInterface $spanContext,
        public readonly Attributes $attributes,
    ) {}
}
