<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal;

use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\CardinalityLimitResolver;
use Nevay\OtelSDK\Metrics\ExemplarReservoirResolver;
use Nevay\OtelSDK\Metrics\MetricReader;
use Nevay\OtelSDK\Metrics\TemporalityResolver;

final class MetricReaderConfiguration {

    public function __construct(
        public readonly MetricReader $metricReader,
        public readonly TemporalityResolver $temporalityResolver,
        public readonly AggregationResolver $aggregationResolver,
        public readonly ExemplarReservoirResolver $exemplarReservoirResolver,
        public readonly CardinalityLimitResolver $cardinalityLimitResolver,
    ) {}
}
