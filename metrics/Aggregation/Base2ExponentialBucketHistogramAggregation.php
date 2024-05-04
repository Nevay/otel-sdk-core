<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use InvalidArgumentException;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;
use function sprintf;

/**
 * The Base2 Exponential Histogram Aggregation informs the SDK to collect data
 * for the Exponential Histogram Metric Point, which uses a base-2 exponential
 * formula to determine bucket boundaries and an integer scale parameter to
 * control resolution.
 *
 * This Aggregation informs the SDK to collect:
 * - Count of Measurement values in population.
 * - Arithmetic sum of Measurement values in population.
 * - Min (optional) Measurement value in population.
 * - Max (optional) Measurement value in population.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#base2-exponential-bucket-histogram-aggregation
 */
final class Base2ExponentialBucketHistogramAggregation implements Aggregation {

    /**
     * @param int<2, max> $maxSize maximum number of buckets in each of positive
     *        and negative ranges
     * @param int<-10, 20> $maxScale maximum scale factor
     * @param bool $recordMinMax whether to record min and max
     */
    public function __construct(
        private readonly int $maxSize = 160,
        private readonly int $maxScale = 20,
        private readonly bool $recordMinMax = true,
    ) {
        if ($this->maxSize < 2) {
            throw new InvalidArgumentException(sprintf('Maximum size (%d) must be greater than or equal to 2', $this->maxSize));
        }
        if ($this->maxScale < -10 || $this->maxScale > 20) {
            throw new InvalidArgumentException(sprintf('Maximum scale (%d) must be between -10 and 20 (inclusive)', $this->maxScale));
        }
    }

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator {
        return match ($instrumentType) {
            InstrumentType::Histogram,
            InstrumentType::Counter,
                => new Base2ExponentialBucketHistogramAggregator(
                    maxSize: $this->maxSize,
                    maxScale: $this->maxScale,
                    recordMinMax: $this->recordMinMax,
                    recordSum: true,
                ),
            InstrumentType::Gauge,
            InstrumentType::UpDownCounter,
                => new Base2ExponentialBucketHistogramAggregator(
                    maxSize: $this->maxSize,
                    maxScale: $this->maxScale,
                    recordMinMax: $this->recordMinMax,
                    recordSum: false,
                ),
            default,
                => null,
        };
    }
}
