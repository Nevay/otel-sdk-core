<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar;

use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use Nevay\OtelSDK\Metrics\ExemplarReservoirFactory;
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
