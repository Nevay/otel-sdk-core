<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\Internal\View\ViewRegistry;

final class MeterState {

    /**
     * @param Configurator<MeterConfig> $configurator
     * @param array<MetricReader> $metricReaders
     * @param Closure(Aggregator): ExemplarReservoir $exemplarReservoir
     */
    public function __construct(
        public Configurator $configurator,
        public array $metricReaders,
        public ViewRegistry $viewRegistry,
        public Closure $exemplarReservoir,
        public ExemplarFilter $exemplarFilter,
        public Resource $resource,
    ) {}
}
