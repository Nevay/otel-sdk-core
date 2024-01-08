<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;

/**
 * @template TSummary
 * @template-covariant TData of Data
 */
interface Aggregation {

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
     * @param array<Attributes> $attributes
     * @param array<TSummary> $summaries
     * @param array<iterable<Exemplar>> $exemplars
     * @return TData
     */
    public function toData(
        array $attributes,
        array $summaries,
        array $exemplars,
        int $startTimestamp,
        int $timestamp,
        Temporality $temporality,
    ): Data;
}
