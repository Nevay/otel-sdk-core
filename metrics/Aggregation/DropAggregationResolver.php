<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\AggregationResolver;
use Nevay\OTelSDK\Metrics\InstrumentType;

final class DropAggregationResolver implements AggregationResolver {

    public function resolveAggregation(InstrumentType $instrumentType, array $advisory = []): Aggregation {
        return new DropAggregation();
    }
}
