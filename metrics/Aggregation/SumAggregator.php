<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\NumberDataPoint;
use Nevay\OTelSDK\Metrics\Data\Sum;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;

/**
 * @implements Aggregator<SumSummary, Sum, NumberDataPoint>
 *
 * @internal
 */
final class SumAggregator implements Aggregator {

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
    ): Sum {
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
