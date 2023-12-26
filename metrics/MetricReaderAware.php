<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

interface MetricReaderAware {

    public function setMetricReader(MetricReader $metricReader): void;
}
