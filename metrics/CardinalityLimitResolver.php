<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

/**
 * @experimental
 */
interface CardinalityLimitResolver {

    /**
     * @return int<0, max>|null
     */
    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int;
}
