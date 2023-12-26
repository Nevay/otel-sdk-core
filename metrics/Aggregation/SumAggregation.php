<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Aggregation;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\NumberDataPoint;
use Nevay\OtelSDK\Metrics\Data\Sum;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;

/**
 * @implements Aggregation<SumSummary, Sum>
 */
final class SumAggregation implements Aggregation {

    public function __construct(
        public readonly bool $monotonic = false,
    ) {}

    public function initialize(): SumSummary {
        return new SumSummary(0);
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        $summary->value += $value;
    }

    public function merge(mixed $left, mixed $right): SumSummary {
        $sum = $right->value + $left->value;

        return new SumSummary($sum);
    }

    public function diff(mixed $left, mixed $right): SumSummary {
        $sum = $right->value - $left->value;

        return new SumSummary($sum);
    }

    public function toData(
        array $attributes,
        array $summaries,
        array $exemplars,
        int $startTimestamp,
        int $timestamp,
        Temporality $temporality,
    ): Sum {
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

        return new Sum(
            $dataPoints,
            $temporality,
            $this->monotonic,
        );
    }
}
