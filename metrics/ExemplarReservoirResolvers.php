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

    public static function fromFactory(?ExemplarReservoirFactory $exemplarReservoirFactory): ExemplarReservoirResolver {
        return new class($exemplarReservoirFactory) implements ExemplarReservoirResolver {

            public function __construct(
                private readonly ?ExemplarReservoirFactory $exemplarReservoirFactory,
            ) {}

            public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoirFactory {
                return $this->exemplarReservoirFactory;
            }
        };
    }

    public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoirFactory {
        return match ($this) {
            self::All => $aggregation instanceof ExplicitBucketHistogramAggregation && $aggregation->boundaries
                ? new AlignedHistogramBucketExemplarReservoirFactory($aggregation->boundaries)
                : new SimpleFixedSizeExemplarReservoirFactory(),
            self::WithSampledTrace => new FilteredReservoirFactory(self::All->resolveExemplarReservoir($aggregation), new WithSampledTraceExemplarFilter()),
            self::None => null,
        };
    }
}
