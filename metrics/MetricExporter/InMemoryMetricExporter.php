<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\MetricExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OtelSDK\Metrics\Data\Metric;
use Nevay\OtelSDK\Metrics\MetricExporter;

final class InMemoryMetricExporter implements MetricExporter {

    /** @var list<Metric> */
    private array $metrics = [];

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        foreach ($batch as $metric) {
            $this->metrics[] = $metric;
        }

        return Future::complete(true);
    }

    /**
     * @return list<Metric>
     */
    public function collect(bool $reset = false): array {
        $metrics = $this->metrics;
        if ($reset) {
            $this->metrics = [];
        }

        return $metrics;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
