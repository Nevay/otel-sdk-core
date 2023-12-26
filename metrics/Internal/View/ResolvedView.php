<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\View;

use Nevay\OtelSDK\Metrics\AggregationResolver;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\ExemplarReservoirResolver;
use Nevay\OtelSDK\Metrics\Internal\MeterMetricProducer;

final class ResolvedView {

    /**
     * @param iterable<MeterMetricProducer> $producers
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $unit,
        public readonly ?string $description,
        public readonly ?AttributeProcessor $attributeProcessor,
        public readonly ?AggregationResolver $aggregationResolver,
        public readonly ?ExemplarReservoirResolver $exemplarReservoirResolver,
        public readonly iterable $producers,
    ) {}
}
