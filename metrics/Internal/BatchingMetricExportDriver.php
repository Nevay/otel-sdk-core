<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Future;
use Amp\TimeoutCancellation;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\Internal\Export\Exporter;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessorDriver;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Throwable;
use function Amp\async;
use function array_splice;
use function count;

/**
 * @implements ExportingProcessorDriver<iterable<Metric>, iterable<Metric>>
 *
 * @internal
 */
final class BatchingMetricExportDriver implements ExportingProcessorDriver {

    public function __construct(
        private readonly MetricProducer $metricProducer,
        private readonly ?MetricFilter $metricFilter,
        private readonly int $collectTimeoutMillis,
        private readonly int $batchSize,
        public Resource $resource = new Resource(new Attributes([])),
    ) {}

    public function getPending(): iterable {
        return $this->metricProducer->produce($this->resource, $this->metricFilter, new TimeoutCancellation($this->collectTimeoutMillis / 1000));
    }

    public function hasPending(): bool {
        return true;
    }

    public function isBuffered(): bool {
        return false;
    }

    public function count(mixed $data): ?int {
        return null;
    }

    public function export(Exporter $exporter, mixed $data, ?Cancellation $cancellation = null): Future {
        $futures = [];
        $batch = [];
        $remaining = $this->batchSize;
        foreach ($data as $metric) {
            $remaining -= count($metric->data->dataPoints);

            do {
                $carry = null;
                $batch[] = $metric;
                if ($remaining < 0) {
                    $carry = new Metric(
                        resource: $metric->resource,
                        descriptor: $metric->descriptor,
                        data: clone $metric->data,
                    );
                    $carry->data->dataPoints = array_splice($metric->data->dataPoints, $remaining);
                }
                unset($metric);
                if ($remaining <= 0) {
                    $remaining += $this->batchSize;
                    try {
                        /** @noinspection PhpMethodParametersCountMismatchInspection */
                        $futures[] = $exporter->export($batch, $cancellation, ...($batch = []));
                    } catch (Throwable $e) {
                        $futures[] = Future::error($e);
                    }
                }
            } while ($metric = $carry);
        }

        if ($batch) {
            try {
                /** @noinspection PhpMethodParametersCountMismatchInspection,PhpUnusedLocalVariableInspection */
                $futures[] = $exporter->export($batch, $cancellation, ...($batch = []));
            } catch (Throwable $e) {
                $futures[] = Future::error($e);
            }
        }

        return async(static function(array $futures): bool {
            [$errors, $results] = Future\awaitAll($futures);

            foreach ($errors as $error) {
                throw $error;
            }

            foreach ($results as $result) {
                if (!$result) {
                    return false;
                }
            }

            return true;
        }, $futures);
    }
}
