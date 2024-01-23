<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

/**
 * @template TSummary
 */
interface MetricAggregator {

    public function record(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void;

    /**
     * @param int $timestamp collection timestamp
     * @return Metric<TSummary>
     */
    public function collect(int $timestamp): Metric;
}
