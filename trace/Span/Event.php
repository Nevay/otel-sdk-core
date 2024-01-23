<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Span;

use Nevay\OTelSDK\Common\Attributes;

final class Event {

    public function __construct(
        public readonly string $name,
        public readonly Attributes $attributes,
        public readonly int $timestamp,
    ) {}
}
