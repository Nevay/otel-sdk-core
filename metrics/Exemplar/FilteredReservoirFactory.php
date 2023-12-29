<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar;

use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use Nevay\OtelSDK\Metrics\ExemplarReservoirFactory;

final class FilteredReservoirFactory implements ExemplarReservoirFactory {

    public function __construct(
        private readonly ExemplarReservoirFactory $factory,
        private readonly ExemplarFilter $filter,
    ) {}

    public function createExemplarReservoir(): ExemplarReservoir {
        return new FilteredReservoir($this->factory->createExemplarReservoir(), $this->filter);
    }
}
