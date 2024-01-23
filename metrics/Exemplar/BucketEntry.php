<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * @internal
 */
final class BucketEntry {

    public float|int $value;
    public int $timestamp;
    public Attributes $attributes;
    public ?SpanContextInterface $spanContext;
}
