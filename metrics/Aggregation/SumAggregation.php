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
        public readonly bool $monotonic,
    ) {}

    public function initialize(): SumSummary {
        return new SumSummary(0, 0);
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        self::calculateCompensatedSum($summary, $value);

    }

    public function merge(mixed $left, mixed $right): SumSummary {
        $right = clone $right;
        self::calculateCompensatedSum($right, $left->value);
        self::calculateCompensatedSum($right, -$left->valueCompensation);

        return $right;
    }

    public function diff(mixed $left, mixed $right): SumSummary {
        $right = clone $right;
        self::calculateCompensatedSum($right, -$left->value);
        self::calculateCompensatedSum($right, $left->valueCompensation);

        return $right;
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

    private static function calculateCompensatedSum(SumSummary $summary, float|int $value): void {
        $y = $value - $summary->valueCompensation;
        $t = $summary->value + $y;
        $summary->valueCompensation = $t - $summary->value - $y;
        $summary->value = $t;
    }
}
