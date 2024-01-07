<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;

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
