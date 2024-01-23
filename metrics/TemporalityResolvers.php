<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Data\Temporality;

enum TemporalityResolvers implements TemporalityResolver {

    case Delta;
    case Cumulative;
    case LowMemory;

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return match ($this) {
            self::Delta => Temporality::Delta,
            self::Cumulative => Temporality::Cumulative,
            self::LowMemory => $descriptor->temporality,
        };
    }
}
