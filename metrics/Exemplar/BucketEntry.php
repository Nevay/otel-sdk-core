<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar;

use Nevay\OtelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * @internal
 */
final class BucketEntry {

    public int|string $index;
    public float|int $value;
    public int $timestamp;
    public Attributes $attributes;
    public ?SpanContextInterface $spanContext;
}
