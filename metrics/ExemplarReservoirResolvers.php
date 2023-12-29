<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Closure;
use Nevay\OtelSDK\Metrics\Aggregation\ExplicitBucketHistogramAggregation;
use Nevay\OtelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoirFactory;
use Nevay\OtelSDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use Nevay\OtelSDK\Metrics\Exemplar\FilteredReservoirFactory;
use Nevay\OtelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoirFactory;

enum ExemplarReservoirResolvers implements ExemplarReservoirResolver {

    case All;
    case WithSampledTrace;
    case None;

    /**
     * @param Closure(Aggregation): ?ExemplarReservoirFactory $callback
     * @return ExemplarReservoirResolver
     */
    public static function callback(Closure $callback): ExemplarReservoirResolver {
        return new class($callback) implements ExemplarReservoirResolver {

            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoirFactory {
                return ($this->callback)($aggregation);
            }
        };
    }

    public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoirFactory {
        return match ($this) {
            self::All,
                => self::defaultExemplarReservoirFactory($aggregation),
            self::WithSampledTrace,
                => new FilteredReservoirFactory(self::defaultExemplarReservoirFactory($aggregation), new WithSampledTraceExemplarFilter()),
            self::None,
                => null,
        };
    }

    private static function defaultExemplarReservoirFactory(Aggregation $aggregation): ExemplarReservoirFactory {
        if ($aggregation instanceof ExplicitBucketHistogramAggregation && $aggregation->boundaries) {
            return new AlignedHistogramBucketExemplarReservoirFactory($aggregation->boundaries);
        }

        return new SimpleFixedSizeExemplarReservoirFactory();
    }
}
