<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricCollector;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricFilterResult;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Traversable;
use function array_keys;
use function count;
use const COUNT_RECURSIVE;

/**
 * @internal
 */
final class MeterMetricProducer implements MetricProducer {

    private readonly MetricCollector $collector;

    /** @var array<int, list<MetricStreamSource>> */
    private array $sources = [];
    /** @var list<int>|null */
    private ?array $streamIds = null;

    public function __construct(MetricCollector $collector) {
        $this->collector = $collector;
    }

    public function unregisterStream(int $streamId): void {
        if (!isset($this->sources[$streamId])) {
            return;
        }

        unset($this->sources[$streamId]);
        $this->streamIds = null;
    }

    public function registerMetricSource(int $streamId, MetricStreamSource $streamSource): void {
        $this->sources[$streamId][] = $streamSource;
        $this->streamIds = null;
    }

    public function produce(?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable {
        $sources = $this->applyMetricFilter($this->sources, $metricFilter);
        $streamIds = count($sources) === count($this->sources)
            ? $this->streamIds ??= array_keys($this->sources)
            : array_keys($sources);
        $collector = $this->collector;

        return new SizedTraversable(
            (static function() use ($collector, $sources, $streamIds, $cancellation): Traversable {
                $collector->collectAndPush($streamIds, $cancellation);
                unset($streamIds, $cancellation);

                foreach ($sources as $streamSources) {
                    foreach ($streamSources as $source) {
                        yield new Metric(
                            $source->descriptor,
                            $source->stream->collect($source->reader),
                        );
                    }
                }
            })(),
            count($sources, COUNT_RECURSIVE) - count($sources),
        );
    }

    /**
     * @param array<int, list<MetricStreamSource>> $sources
     * @return array<int, array<int, MetricStreamSource>>
     */
    private function applyMetricFilter(array $sources, ?MetricFilter $filter): array {
        foreach ($sources as $streamId => $streamSources) {
            foreach ($streamSources as $sourceId => $source) {
                $result = self::testMetric($source, $filter);

                if ($result === MetricFilterResult::Accept) {
                    // no-op
                }
                if ($result === MetricFilterResult::AcceptPartial) {
                    $sources[$streamId][$sourceId] = new MetricStreamSource(
                        $source->descriptor,
                        new FilteredMetricStream($source->descriptor, $source->stream, $filter),
                        $source->reader,
                        $source->meterConfig,
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

    private static function testMetric(MetricStreamSource $source, ?MetricFilter $filter): MetricFilterResult {
        if ($source->meterConfig->disabled) {
            return MetricFilterResult::Drop;
        }

        return $filter?->testMetric(
            $source->descriptor->instrumentationScope,
            $source->descriptor->name,
            $source->descriptor->instrumentType,
            $source->descriptor->unit,
        ) ?? MetricFilterResult::Accept;
    }
}
