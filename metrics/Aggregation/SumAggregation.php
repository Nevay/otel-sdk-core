<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\InstrumentType;

final class SumAggregation implements Aggregation {

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator {
        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::Histogram,
            InstrumentType::AsynchronousCounter,
                => new SumAggregator(true),
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new SumAggregator(false),
            default,
                => null,
        };
    }
}
