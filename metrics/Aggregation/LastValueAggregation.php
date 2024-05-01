<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\InstrumentType;

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
