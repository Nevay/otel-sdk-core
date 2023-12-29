<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

interface ExemplarReservoirResolver {

    public function resolveExemplarReservoir(Aggregation $aggregation): ?ExemplarReservoirFactory;
}
