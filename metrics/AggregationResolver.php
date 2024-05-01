<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

interface AggregationResolver {

    public function resolveAggregation(InstrumentType $instrumentType): Aggregation;
}
