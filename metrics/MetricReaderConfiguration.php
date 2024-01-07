<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

final class MetricReaderConfiguration {

    public function __construct(
        public readonly MetricReader $metricReader,
        public readonly TemporalityResolver $temporalityResolver,
    ) {}
}
