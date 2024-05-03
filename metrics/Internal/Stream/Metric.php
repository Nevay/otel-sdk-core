<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

/**
 * @template TSummary
 */
final class Metric {

    /**
     * @param array<MetricPoint<TSummary>> $metricPoints
     */
    public function __construct(
        public array $metricPoints,
        public int $timestamp,
    ) {}
}
