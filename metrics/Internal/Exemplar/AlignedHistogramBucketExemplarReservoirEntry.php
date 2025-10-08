<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * @internal
 */
final class AlignedHistogramBucketExemplarReservoirEntry {

    public float $priority = 0;
    public int $jumpWeight = 0;

    public float|int $value;
    public int $timestamp;
    public Attributes $attributes;
    public ?SpanContextInterface $spanContext;
}
