<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

use InvalidArgumentException;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;
use function assert;
use function spl_object_id;
use function sprintf;

/**
 * The Default Aggregation informs the SDK to use the Instrument kind to select
 * an aggregation and advisory parameters to influence aggregation configuration
 * parameters.
 *
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#default-aggregation
 */
final class DefaultAggregation implements Aggregation {

    /** @var array<int, Aggregation> */
    private array $aggregations = [];

    /**
     * Overrides the default behavior for the given instrument type.
     *
     * @param InstrumentType $instrumentType instrument type to override
     * @param Aggregation $aggregation aggregation to use for the given
     *        instrument type
     * @return Aggregation a new aggregation that uses the given aggregation
     *         for the given instrument type
     */
    public function with(InstrumentType $instrumentType, Aggregation $aggregation): Aggregation {
        if (!$aggregation->aggregator($instrumentType)) {
            throw new InvalidArgumentException(sprintf('Aggregation "%s" does not support instrument type "%s"', $aggregation::class, $instrumentType->name));
        }

        $self = clone $this;
        $self->aggregations[spl_object_id($instrumentType)] = $aggregation;

        return $self;
    }

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): Aggregator {
        $aggregator = $this->aggregation($instrumentType)->aggregator($instrumentType, $advisory);
        assert($aggregator !== null);

        return $aggregator;
    }

    private function aggregation(InstrumentType $instrumentType): Aggregation {
        if ($aggregation = $this->aggregations[spl_object_id($instrumentType)] ?? null) {
            return $aggregation;
        }

        return match ($instrumentType) {
            InstrumentType::Counter,
            InstrumentType::AsynchronousCounter,
            InstrumentType::UpDownCounter,
            InstrumentType::AsynchronousUpDownCounter,
                => new SumAggregation(),
            InstrumentType::Histogram,
                => new ExplicitBucketHistogramAggregation(),
            InstrumentType::Gauge,
            InstrumentType::AsynchronousGauge,
                => new LastValueAggregation(),
        };
    }
}
