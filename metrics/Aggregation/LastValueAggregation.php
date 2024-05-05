<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\LastValueAggregator;

/**
 * The Last Value Aggregation informs the SDK to collect data for the Gauge Metric Point.
 *
 * This Aggregation informs the SDK to collect:
 * - The last Measurement.
 * - The timestamp of the last Measurement.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#last-value-aggregation
 */
final class LastValueAggregation implements Aggregation {

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator {
        return match ($instrumentType) {
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new LastValueAggregator(),
            default,
                => null,
        };
    }
}
