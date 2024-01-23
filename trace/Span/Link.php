<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Span;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

final class Link {

    public function __construct(
        public readonly SpanContextInterface $spanContext,
        public readonly Attributes $attributes,
    ) {}
}
