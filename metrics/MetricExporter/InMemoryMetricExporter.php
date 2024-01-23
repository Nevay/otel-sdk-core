<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\MetricExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\AggregationResolver;
use Nevay\OTelSDK\Metrics\AggregationResolvers;
use Nevay\OTelSDK\Metrics\CardinalityLimitResolver;
use Nevay\OTelSDK\Metrics\CardinalityLimitResolvers;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\MetricExporter;
use Nevay\OTelSDK\Metrics\TemporalityResolver;
use Nevay\OTelSDK\Metrics\TemporalityResolvers;

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
