<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

/**
 * @experimental
 */
interface CardinalityLimitResolver {

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int;
}
