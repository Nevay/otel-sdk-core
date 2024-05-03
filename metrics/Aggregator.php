<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;

/**
 * @template TSummary
 * @template-covariant TData of Data<TDataPoint>
 * @template-covariant TDataPoint of DataPoint
 */
interface Aggregator {

    /**
     * @return TSummary
     */
    public function initialize(): mixed;

    /**
     * @param TSummary $summary
     */
    public function record(
        mixed $summary,
        float|int $value,
        Attributes $attributes,
        ContextInterface $context,
        int $timestamp,
    ): void;

    /**
     * @param TSummary $left
     * @param TSummary $right
     * @return TSummary
     */
    public function merge(mixed $left, mixed $right): mixed;

    /**
     * @param TSummary $left
     * @param TSummary $right
     * @return TSummary
     */
    public function diff(mixed $left, mixed $right): mixed;

    /**
     * @param TSummary $summary
     * @param iterable $exemplars
     * @return TDataPoint
     */
    public function toDataPoint(
        Attributes $attributes,
        mixed $summary,
        iterable $exemplars,
        int $startTimestamp,
        int $timestamp,
    ): DataPoint;

    /**
     * @param array<TDataPoint> $dataPoints
     * @return TData
     */
    public function toData(
        array $dataPoints,
        Temporality $temporality,
    ): Data;
}
