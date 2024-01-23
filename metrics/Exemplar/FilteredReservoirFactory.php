<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\ExemplarReservoirFactory;

final class FilteredReservoirFactory implements ExemplarReservoirFactory {

    public function __construct(
        private readonly ExemplarReservoirFactory $factory,
        private readonly ExemplarFilter $filter,
    ) {}

    public function createExemplarReservoir(): ExemplarReservoir {
        return new FilteredReservoir($this->factory->createExemplarReservoir(), $this->filter);
    }
}
