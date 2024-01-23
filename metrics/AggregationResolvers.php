<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Aggregation\ExplicitBucketHistogramAggregationResolver;
use Nevay\OTelSDK\Metrics\Aggregation\LastValueAggregationResolver;
use Nevay\OTelSDK\Metrics\Aggregation\SumAggregationResolver;
use function assert;

enum AggregationResolvers implements AggregationResolver {

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#default-aggregation
     */
    case Default;

    /**
     * @param Aggregation|null $aggregation
     * @return AggregationResolver
     */
    public static function fromAggregation(?Aggregation $aggregation): AggregationResolver {
        return new class($aggregation) implements AggregationResolver {

            public function __construct(
                private readonly ?Aggregation $aggregation,
            ) {}

            public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
                return $this->aggregation;
            }
        };
    }

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): Aggregation {
        $aggregation = $this->resolver($instrumentType)->resolveAggregation($instrumentType, $advisory);
        assert($aggregation !== null);

        return $aggregation;
    }

    private function resolver(InstrumentType $instrumentType): AggregationResolver {
        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::AsynchronousCounter,
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new SumAggregationResolver(),
            InstrumentType::Histogram,
                => new ExplicitBucketHistogramAggregationResolver(),
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new LastValueAggregationResolver(),
        };
    }
}
