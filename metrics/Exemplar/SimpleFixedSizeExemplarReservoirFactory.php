<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\ExemplarReservoirFactory;
use Random\Randomizer;

final class SimpleFixedSizeExemplarReservoirFactory implements ExemplarReservoirFactory {

    public function __construct(
        private readonly int $size = 1,
        private readonly ?Randomizer $randomizer = null,
    ) {}

    public function createExemplarReservoir(): ExemplarReservoir {
        return new SimpleFixedSizeExemplarReservoir($this->size, $this->randomizer);
    }
}
