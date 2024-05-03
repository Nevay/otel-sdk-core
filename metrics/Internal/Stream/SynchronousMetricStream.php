<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use GMP;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use function assert;
use function extension_loaded;
use function gmp_init;
use function is_int;
use function sprintf;
use function trigger_error;
use const E_USER_WARNING;
use const PHP_INT_SIZE;

/**
 * @template TSummary
 * @template-covariant TData of Data
 * @implements MetricStream<TSummary, TData>
 */
final class SynchronousMetricStream implements MetricStream {

    /** @var Aggregator<TSummary, TData, DataPoint> */
    private Aggregator $aggregator;
    private int $timestamp;
    /** @var DeltaStorage<TSummary> */
    private DeltaStorage $storage;
    private int|GMP $readers = 0;
    private int|GMP $cumulative = 0;

    /**
     * @param Aggregator<TSummary, TData, DataPoint> $aggregator
     */
    public function __construct(Aggregator $aggregator, int $startTimestamp, ?int $cardinalityLimit) {
        $this->aggregator = $aggregator;
        $this->timestamp = $startTimestamp;
        $this->storage = new DeltaStorage($aggregator, $cardinalityLimit);
    }

    public function temporality(): Temporality {
        return Temporality::Delta;
    }

    public function timestamp(): int {
        return $this->timestamp;
    }

    public function push(Metric $metric): void {
        [$this->timestamp, $metric->timestamp] = [$metric->timestamp, $this->timestamp];
        $this->storage->add($metric, $this->readers);
    }

    public function register(Temporality $temporality): int {
        $reader = 0;
        for ($r = $this->readers; ($r & 1) != 0; $r >>= 1, $reader++) {}
        if ($reader === (PHP_INT_SIZE << 3) - 1 && is_int($this->readers)) {
            if (!extension_loaded('gmp')) {
                trigger_error(sprintf('GMP extension required to register over %d readers', (PHP_INT_SIZE << 3) - 1), E_USER_WARNING);
                $reader = PHP_INT_SIZE << 3;
            } else {
                assert(is_int($this->cumulative));
                $this->readers = gmp_init($this->readers);
                $this->cumulative = gmp_init($this->cumulative);
            }
        }

        $readerMask = ($this->readers & 1 | 1) << $reader;
        $this->readers ^= $readerMask;
        if ($temporality === Temporality::Cumulative) {
            $this->cumulative ^= $readerMask;
        }

        return $reader;
    }

    public function unregister(int $reader): void {
        $readerMask = ($this->readers & 1 | 1) << $reader;
        if (($this->readers & $readerMask) == 0) {
            return;
        }

        $this->storage->collect($reader);

        $this->readers ^= $readerMask;
        if (($this->cumulative & $readerMask) != 0) {
            $this->cumulative ^= $readerMask;
        }
    }

    public function collect(int $reader): Data {
        $cumulative = ($this->cumulative >> $reader & 1) != 0;
        $metric = $this->storage->collect($reader, $cumulative) ?? new Metric([], $this->timestamp);

        $temporality = $cumulative
            ? Temporality::Cumulative
            : Temporality::Delta;

        $dataPoints = [];
        foreach ($metric->metricPoints as $metricPoint) {
            $dataPoints[] = $this->aggregator->toDataPoint(
                $metricPoint->attributes,
                $metricPoint->summary,
                $metricPoint->exemplars,
                $metric->timestamp,
                $this->timestamp,
            );
        }

        return $this->aggregator->toData(
            $dataPoints,
            $temporality,
        );
    }
}
