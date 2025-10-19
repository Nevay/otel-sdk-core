<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Closure;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoir;
use Nevay\OTelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\ExplicitBucketHistogramAggregator;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

/**
 * @internal
 */
final class ExemplarReservoirs {

    public static function defaultFactory(): Closure {
        $randomizer = new Randomizer(new PcgOneseq128XslRr64());

        return static fn(Aggregator $aggregator) => $aggregator instanceof ExplicitBucketHistogramAggregator && $aggregator->boundaries
            ? new AlignedHistogramBucketExemplarReservoir($aggregator->boundaries, $randomizer)
            : new SimpleFixedSizeExemplarReservoir(1, $randomizer);
    }
}
