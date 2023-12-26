<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Span;

use Nevay\OtelSDK\Common\Attributes;

final class Event {

    public function __construct(
        public readonly string $name,
        public readonly Attributes $attributes,
        public readonly int $timestamp,
    ) {}
}
