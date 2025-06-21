<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Metrics\Data\Temporality;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\TemporalityResolver;

/**
 * @internal
 */
enum TemporalityResolvers implements TemporalityResolver {

    case DeltaResolver;
    case CumulativeResolver;
    case LowMemoryResolver;

    public function resolveTemporality(InstrumentType $instrumentType, Temporality $preferredTemporality): Temporality {
        return match ($this) {
            self::DeltaResolver => Temporality::Delta,
            self::CumulativeResolver => Temporality::Cumulative,
            self::LowMemoryResolver => $preferredTemporality,
        };
    }
}
