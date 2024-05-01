<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

interface ExemplarReservoirResolver {

    public function resolveExemplarReservoir(Aggregator $aggregator): ?ExemplarReservoirFactory;
}
