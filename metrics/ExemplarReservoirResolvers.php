<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Aggregation\ExplicitBucketHistogramAggregator;
use Nevay\OTelSDK\Metrics\Exemplar\AlignedHistogramBucketExemplarReservoirFactory;
use Nevay\OTelSDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use Nevay\OTelSDK\Metrics\Exemplar\FilteredReservoirFactory;
use Nevay\OTelSDK\Metrics\Exemplar\SimpleFixedSizeExemplarReservoirFactory;

enum ExemplarReservoirResolvers implements ExemplarReservoirResolver {

    case All;
    case WithSampledTrace;
    case None;

    public static function fromFactory(?ExemplarReservoirFactory $exemplarReservoirFactory): ExemplarReservoirResolver {
        return new class($exemplarReservoirFactory) implements ExemplarReservoirResolver {

            public function __construct(
                private readonly ?ExemplarReservoirFactory $exemplarReservoirFactory,
            ) {}

            public function resolveExemplarReservoir(Aggregator $aggregator): ?ExemplarReservoirFactory {
                return $this->exemplarReservoirFactory;
            }
        };
    }

    public function resolveExemplarReservoir(Aggregator $aggregator): ?ExemplarReservoirFactory {
        return match ($this) {
            self::All => $aggregator instanceof ExplicitBucketHistogramAggregator && $aggregator->boundaries
                ? new AlignedHistogramBucketExemplarReservoirFactory($aggregator->boundaries)
                : new SimpleFixedSizeExemplarReservoirFactory(),
            self::WithSampledTrace => new FilteredReservoirFactory(self::All->resolveExemplarReservoir($aggregator), new WithSampledTraceExemplarFilter()),
            self::None => null,
        };
    }
}
