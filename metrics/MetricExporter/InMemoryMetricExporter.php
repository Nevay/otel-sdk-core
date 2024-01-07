<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\MetricExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\AggregationResolvers;
use Nevay\OtelSDK\Metrics\CardinalityLimitResolver;
use Nevay\OtelSDK\Metrics\CardinalityLimitResolvers;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Metric;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use Nevay\OtelSDK\Metrics\InstrumentType;
use Nevay\OtelSDK\Metrics\MetricExporter;
use Nevay\OtelSDK\Metrics\TemporalityResolver;
use Nevay\OtelSDK\Metrics\TemporalityResolvers;

final class InMemoryMetricExporter implements MetricExporter {

    /** @var list<Metric> */
    private array $metrics = [];

    public function __construct(
        private readonly TemporalityResolver $temporalityResolver = TemporalityResolvers::LowMemory,
        private readonly AggregationResolver $aggregationResolver = AggregationResolvers::Default,
        private readonly CardinalityLimitResolver $cardinalityLimitResolver = CardinalityLimitResolvers::Default,
    ) {}

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        foreach ($batch as $metric) {
            $this->metrics[] = $metric;
        }

        return Future::complete(true);
    }

    /**
     * @return list<Metric>
     */
    public function collect(bool $reset = false): array {
        $metrics = $this->metrics;
        if ($reset) {
            $this->metrics = [];
        }

        return $metrics;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return $this->temporalityResolver->resolveTemporality($descriptor);
    }

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return $this->aggregationResolver->resolveAggregation($instrumentType, $advisory);
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return $this->cardinalityLimitResolver->resolveCardinalityLimit($instrumentType);
    }
}
