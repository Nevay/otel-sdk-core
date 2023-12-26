<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Closure;

enum AggregationResolvers implements AggregationResolver {

    /**
     * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#default-aggregation
     */
    case Default;

    /**
     * @param Aggregation $aggregation
     * @return AggregationResolver
     */
    public static function resolved(Aggregation $aggregation): AggregationResolver {
        return new class($aggregation) implements AggregationResolver {

            public function __construct(
                private readonly ?Aggregation $aggregation,
            ) {}

            public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
                return $this->aggregation;
            }
        };
    }

    /**
     * @param Closure(InstrumentType, array): ?Aggregation $callback
     * @return AggregationResolver
     */
    public static function callback(Closure $callback): AggregationResolver {
        return new class($callback) implements AggregationResolver {

            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
                return ($this->callback)($instrumentType, $advisory);
            }
        };
    }

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::AsynchronousCounter,
                => new Aggregation\SumAggregation(true),
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new Aggregation\SumAggregation(),
            InstrumentType::Histogram,
                => new Aggregation\ExplicitBucketHistogramAggregation(boundaries: $advisory['ExplicitBucketBoundaries'] ?? [0, 5, 10, 25, 50, 75, 100, 250, 500, 1000, 2500, 5000, 7500, 10000]),
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new Aggregation\LastValueAggregation(),
        };
    }
}
