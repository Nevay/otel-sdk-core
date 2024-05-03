<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Closure;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\AttributeProcessor\AttributeProcessor;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarFilter;
use Nevay\OTelSDK\Metrics\Internal\MeterMetricProducer;

final class ResolvedView {

    /**
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly AttributeProcessor $attributeProcessor,
        public readonly Aggregator $aggregator,
        public readonly ExemplarFilter $exemplarFilter,
        public readonly Closure $exemplarReservoir,
        public readonly ?int $cardinalityLimit,
        public readonly MeterMetricProducer $metricProducer,
        public readonly Temporality $temporality,
    ) {}
}
