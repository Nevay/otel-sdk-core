<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Closure;
use Nevay\OtelSDK\Metrics\Aggregation\ExplicitBucketHistogramAggregation;
use Nevay\OtelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoir;
use Nevay\OtelSDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use Nevay\OtelSDK\Metrics\Exemplar\FilteredReservoir;
use Nevay\OtelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoir;

enum ExemplarReservoirResolvers implements ExemplarReservoirResolver {

    case All;
    case WithSampledTrace;
    case None;

    /**
     * @param Closure(Aggregation): ?ExemplarReservoir $callback
     * @return ExemplarReservoirResolver
     */
    public static function callback(Closure $callback): ExemplarReservoirResolver {
        return new class($callback) implements ExemplarReservoirResolver {

            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoir {
                return ($this->callback)($aggregation);
            }
        };
    }

    public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoir {
        return match ($this) {
            self::All,
                => self::defaultExemplarReservoir($aggregation),
            self::WithSampledTrace,
                => new FilteredReservoir(self::defaultExemplarReservoir($aggregation), new WithSampledTraceExemplarFilter()),
            self::None,
                => null,
        };
    }

    private static function defaultExemplarReservoir(Aggregation $aggregation): ExemplarReservoir {
        if ($aggregation instanceof ExplicitBucketHistogramAggregation && $aggregation->boundaries) {
            return new AlignedHistogramBucketExemplarReservoir($aggregation->boundaries);
        }

        return new SimpleFixedSizeExemplarReservoir(1);
    }
}
