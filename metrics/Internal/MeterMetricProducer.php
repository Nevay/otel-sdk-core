<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricCollector;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricFilterResult;
use Nevay\OTelSDK\Metrics\MetricProducer;
use function array_keys;
use function count;

/**
 * @internal
 */
final class MeterMetricProducer implements MetricProducer {

    private readonly MetricCollector $collector;

    /** @var array<int, list<MetricStreamSource>> */
    public array $sources = [];
    /** @var list<int>|null */
    private ?array $streamIds = null;

    public function __construct(MetricCollector $collector) {
        $this->collector = $collector;
    }

    public function unregisterStream(int $streamId): array {
        if ($sources = $this->sources[$streamId] ?? []) {
            $this->streamIds = null;
            unset($this->sources[$streamId]);
        }

        return $sources;
    }

    public function registerMetricSource(int $streamId, MetricStreamSource $streamSource): void {
        $this->sources[$streamId][] = $streamSource;
        $this->streamIds = null;
    }

    public function produce(Resource $resource, ?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable {
        $sources = $metricFilter
            ? $this->applyMetricFilter($this->sources, $metricFilter)
            : $this->sources;
        $streamIds = count($sources) === count($this->sources)
            ? $this->streamIds ??= array_keys($this->sources)
            : array_keys($sources);

        $this->collector->collectAndPush($streamIds, $cancellation);
        unset($streamIds, $metricFilter, $cancellation);

        foreach ($sources as $streamSources) {
            foreach ($streamSources as $source) {
                $data = $source->stream->collect($source->reader);
                if (!$data->dataPoints) {
                    continue;
                }

                yield new Metric($resource, $source->descriptor, $data);
            }
        }
    }

    /**
     * @param array<int, list<MetricStreamSource>> $sources
     * @return array<int, array<int, MetricStreamSource>>
     */
    private function applyMetricFilter(array $sources, MetricFilter $filter): array {
        foreach ($sources as $streamId => $streamSources) {
            foreach ($streamSources as $sourceId => $source) {
                $result = $filter->testMetric(
                    $source->descriptor->instrumentationScope,
                    $source->descriptor->name,
                    $source->descriptor->instrumentType,
                    $source->descriptor->unit,
                );

                if ($result === MetricFilterResult::Accept) {
                    // no-op
                }
                if ($result === MetricFilterResult::AcceptPartial) {
                    $sources[$streamId][$sourceId] = new MetricStreamSource(
                        $source->descriptor,
                        new FilteredMetricStream($source->descriptor, $source->stream, $filter),
                        $source->reader,
                    );
                }
                if ($result === MetricFilterResult::Drop) {
                    unset($sources[$streamId][$sourceId]);
                    if (!$sources[$streamId]) {
                        /** @noinspection PhpConditionAlreadyCheckedInspection */
                        unset($sources[$streamId]);
                    }
                }
            }
        }

        return $sources;
    }
}
