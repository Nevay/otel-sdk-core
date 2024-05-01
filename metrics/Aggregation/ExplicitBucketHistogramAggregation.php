<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\InstrumentType;

/**
 * The Explicit Bucket Histogram Aggregation informs the SDK to collect data for
 * the Histogram Metric Point using a set of explicit boundary values for
 * histogram bucketing.
 *
 * This Aggregation informs the SDK to collect:
 * - Count of Measurement values in population.
 * - Arithmetic sum of Measurement values in population.
 * - Min (optional) Measurement value in population.
 * - Max (optional) Measurement value in population.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#explicit-bucket-histogram-aggregation
 */
final class ExplicitBucketHistogramAggregation implements Aggregation {

    private const DEFAULT_BOUNDARIES = [0, 5, 10, 25, 50, 75, 100, 250, 500, 1000, 2500, 5000, 7500, 10000];

    /**
     * @param list<float>|null $boundaries array of increasing values
     *        representing explicit bucket boundary values
     * @param bool $recordMinMax whether to record min and max
     */
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
