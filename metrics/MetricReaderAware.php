<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

interface MetricReaderAware {

    public function setMetricReader(MetricReader $metricReader): void;
}
