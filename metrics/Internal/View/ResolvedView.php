<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\ExemplarReservoirFactory;
use Nevay\OTelSDK\Metrics\Internal\MeterMetricProducer;

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
