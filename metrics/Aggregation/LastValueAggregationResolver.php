<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Aggregation;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\InstrumentType;

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
