<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\InstrumentType;

final class ExplicitBucketHistogramAggregation implements Aggregation {

    private const DEFAULT_BOUNDARIES = [0, 5, 10, 25, 50, 75, 100, 250, 500, 1000, 2500, 5000, 7500, 10000];

    public function __construct(
        private readonly ?array $boundaries = null,
        private readonly bool $recordMinMax = true,
    ) {}

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator {
        return match ($instrumentType) {
            InstrumentType::Histogram,
                => new ExplicitBucketHistogramAggregator(
                    boundaries: $this->boundaries ?? $advisory['ExplicitBucketBoundaries'] ?? self::DEFAULT_BOUNDARIES,
                    recordMinMax: $this->recordMinMax,
                    recordSum: true,
                ),
            InstrumentType::Counter,
                => new ExplicitBucketHistogramAggregator(
                    boundaries: $this->boundaries ?? self::DEFAULT_BOUNDARIES,
                    recordMinMax: $this->recordMinMax,
                    recordSum: true,
                ),
            InstrumentType::Gauge,
            InstrumentType::UpDownCounter,
                => new ExplicitBucketHistogramAggregator(
                    boundaries: $this->boundaries ?? self::DEFAULT_BOUNDARIES,
                    recordMinMax: $this->recordMinMax,
                    recordSum: false,
                ),
            default,
                => null,
        };
    }
}
