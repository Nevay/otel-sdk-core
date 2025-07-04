<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\Temporality;

/**
 * @template TSummary
 * @template-covariant TData of Data
 *
 * @internal
 */
interface MetricStream {

    /**
     * Returns the internal temporality of this stream.
     *
     * @return Temporality internal temporality
     */
    public function temporality(): Temporality;

    /**
     * Returns the last metric timestamp.
     *
     * @return int metric timestamp
     */
    public function timestamp(): int;

    /**
     * Pushes metric data to the stream.
     *
     * @param Metric<TSummary> $metric metric data to push
     */
    public function push(Metric $metric): void;

    /**
     * Registers a new reader with the given temporality.
     *
     * @param Temporality $temporality temporality to use
     * @return int reader id
     */
    public function register(Temporality $temporality): int;

    /**
     * Unregisters the given reader.
     *
     * @param int $reader reader id
     */
    public function unregister(int $reader): void;

    /**
     * Returns whether this stream has registered readers.
     *
     * @return bool whether this stream has registered readers
     */
    public function hasReaders(): bool;

    /**
     * Collects metric data for the given reader.
     *
     * @param int $reader reader id
     * @return TData metric data
     */
    public function collect(int $reader): Data;
}
