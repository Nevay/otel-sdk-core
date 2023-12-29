<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

interface ExemplarReservoirFactory {

    public function createExemplarReservoir(): ExemplarReservoir;
}
