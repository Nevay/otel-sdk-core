<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\Internal\Aggregation\DropAggregator;

/**
 * The Drop Aggregation informs the SDK to ignore/drop all Instrument
 * Measurements for this Aggregation.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#drop-aggregation
 */
final class DropAggregation implements Aggregation {

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): Aggregator {
        return new DropAggregator();
    }
}
