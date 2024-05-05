<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\Internal\Stream\Metric;
use Nevay\OTelSDK\Metrics\Internal\Stream\MetricStream;
use Nevay\OTelSDK\Metrics\MetricFilter;

/**
 * @template TSummary
 * @template-covariant TData of Data
 * @implements MetricStream<TSummary, TData>
 *
 * @internal
 */
final class FilteredMetricStream implements MetricStream {

    /**
     * @param MetricStream<TSummary, TData> $stream
     */
    public function __construct(
        private readonly Descriptor $descriptor,
        private readonly MetricStream $stream,
        private readonly MetricFilter $filter,
    ) {}

    public function temporality(): Temporality {
        return $this->stream->temporality();
    }

    public function timestamp(): int {
        return $this->stream->timestamp();
    }

    public function push(Metric $metric): void {
        $this->stream->push($metric);
    }

    public function register(Temporality $temporality): int {
        return $this->stream->register($temporality);
    }

    public function unregister(int $reader): void {
        $this->stream->unregister($reader);
    }

    public function collect(int $reader): Data {
        $data = $this->stream->collect($reader);

        foreach ($data->dataPoints as $key => $dataPoint) {
            $result = $this->filter->testAttributes(
                $this->descriptor->instrumentationScope,
                $this->descriptor->name,
                $this->descriptor->instrumentType,
                $this->descriptor->unit,
                $dataPoint->attributes,
            );

            if (!$result) {
                unset($data->dataPoints[$key]);
            }
        }

        return $data;
    }
}
