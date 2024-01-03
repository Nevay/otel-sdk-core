<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Aggregation;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\InstrumentType;

final class SumAggregationResolver implements AggregationResolver {

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): ?Aggregation {
        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::Histogram,
            InstrumentType::AsynchronousCounter,
                => new SumAggregation(true),
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new SumAggregation(false),
            default,
                => null,
        };
    }
}
