<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

use Nevay\OtelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

final class Exemplar {

    public function __construct(
        public readonly float|int $value,
        public readonly int $timestamp,
        public readonly Attributes $attributes,
        public readonly ?SpanContextInterface $spanContext,
    ) {}
}
