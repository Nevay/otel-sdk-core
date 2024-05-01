<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;
use function assert;

/**
 * The Default Aggregation informs the SDK to use the Instrument kind to select
 * an aggregation and advisory parameters to influence aggregation configuration
 * parameters.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#default-aggregation
 */
final class DefaultAggregation implements Aggregation {

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): Aggregator {
        $aggregator = $this->aggregation($instrumentType)->aggregator($instrumentType, $advisory);
        assert($aggregator !== null);

        return $aggregator;
    }

    private function aggregation(InstrumentType $instrumentType): Aggregation {
        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::AsynchronousCounter,
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new SumAggregation(),
            InstrumentType::Histogram,
                => new ExplicitBucketHistogramAggregation(),
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new LastValueAggregation(),
        };
    }
}
