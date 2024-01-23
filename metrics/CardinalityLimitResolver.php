<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

/**
 * @experimental
 */
interface CardinalityLimitResolver {

    /**
     * @return int<0, max>|null
     */
    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int;
}
