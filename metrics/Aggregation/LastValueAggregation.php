<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Data\Gauge;
use Nevay\OTelSDK\Metrics\Data\NumberDataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;
use const NAN;

/**
 * @implements Aggregation<LastValueSummary, Gauge>
 */
final class LastValueAggregation implements Aggregation {

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

    public function toData(
        array $attributes,
        array $summaries,
        array $exemplars,
        int $startTimestamp,
        int $timestamp,
        Temporality $temporality,
    ): Gauge {
        $dataPoints = [];
        foreach ($attributes as $key => $dataPointAttributes) {
            $dataPoints[] = new NumberDataPoint(
                $summaries[$key]->value,
                $dataPointAttributes,
                $startTimestamp,
                $timestamp,
                $exemplars[$key] ?? [],
            );
        }

        return new Gauge(
            $dataPoints,
        );
    }
}
