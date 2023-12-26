<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use GMP;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use Nevay\OtelSDK\Metrics\Data\Temporality;
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

    /** @var Aggregation<TSummary, TData> */
    private Aggregation $aggregation;
    private int $timestamp;
    /** @var DeltaStorage<TSummary> */
    private DeltaStorage $storage;
    private int|GMP $readers = 0;
    private int|GMP $cumulative = 0;

    /**
     * @param Aggregation<TSummary, TData> $aggregation
     */
    public function __construct(Aggregation $aggregation, int $startTimestamp) {
        $this->aggregation = $aggregation;
        $this->timestamp = $startTimestamp;
        $this->storage = new DeltaStorage($aggregation);
    }

    public function temporality(): Temporality {
        return Temporality::Delta;
    }

    public function aggregation(): Aggregation {
        return $this->aggregation;
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
        $metric = $this->storage->collect($reader, $cumulative) ?? new Metric([], [], $this->timestamp);

        $temporality = $cumulative
            ? Temporality::Cumulative
            : Temporality::Delta;

        return $this->aggregation->toData(
            $metric->attributes,
            $metric->summaries,
            Exemplar::groupByIndex($metric->exemplars),
            $metric->timestamp,
            $this->timestamp,
            $temporality,
        );
    }
}
