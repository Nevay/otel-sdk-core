<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\AggregationResolver;
use Nevay\OTelSDK\Metrics\InstrumentType;

final class LastValueAggregationResolver implements AggregationResolver {

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return match ($instrumentType) {
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new LastValueAggregation(),
            default,
                => null,
        };
    }
}
