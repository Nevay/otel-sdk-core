<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

interface ExemplarReservoirFactory {

    public function createExemplarReservoir(): ExemplarReservoir;
}
