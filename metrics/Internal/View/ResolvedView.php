<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\View;

use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use Nevay\OtelSDK\Metrics\ExemplarReservoirFactory;
use Nevay\OtelSDK\Metrics\Internal\MeterMetricProducer;

final class ResolvedView {

    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly ?AttributeProcessor $attributeProcessor,
        public readonly Aggregation $aggregation,
        public readonly ?ExemplarReservoirFactory $exemplarReservoirFactory,
        public readonly ?int $cardinalityLimit,
        public readonly MeterMetricProducer $metricProducer,
        public readonly Temporality $temporality,
    ) {}
}
