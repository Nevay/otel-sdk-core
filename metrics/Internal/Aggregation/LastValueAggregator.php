<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Aggregation;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\Gauge;
use Nevay\OTelSDK\Metrics\Data\NumberDataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;
use const NAN;

/**
 * @implements Aggregator<LastValueSummary, Gauge, NumberDataPoint>
 *
 * @internal
 */
final class LastValueAggregator implements Aggregator {

    public function initialize(): LastValueSummary {
        return new LastValueSummary(NAN, 0);
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        if ($summary->timestamp > $timestamp) {
            return;
        }

        $summary->value = $value;
        $summary->timestamp = $timestamp;
    }

    public function merge(mixed $left, mixed $right): LastValueSummary {
        return $left->timestamp > $right->timestamp ? $left : $right;
    }

    public function diff(mixed $left, mixed $right): LastValueSummary {
        return $left->timestamp > $right->timestamp ? $left : $right;
    }

    public function toDataPoint(
        Attributes $attributes,
        mixed $summary,
        iterable $exemplars,
        int $startTimestamp,
        int $timestamp,
    ): DataPoint {
        return new NumberDataPoint(
            $summary->value,
            $attributes,
            $startTimestamp,
            $timestamp,
            $exemplars,
        );
    }

    public function toData(
        array $dataPoints,
        Temporality $temporality,
    ): Gauge {
        return new Gauge(
            $dataPoints,
        );
    }
}
