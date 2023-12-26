<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Closure;
use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Data\Temporality;

enum TemporalityResolvers implements TemporalityResolver {

    case Delta;
    case Cumulative;
    case LowMemory;

    /**
     * @param Closure(Descriptor): ?Temporality $callback
     */
    public static function callback(Closure $callback): TemporalityResolver {
        return new class($callback) implements TemporalityResolver {

            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function resolveTemporality(Descriptor $descriptor): ?Temporality {
                return ($this->callback)($descriptor);
            }
        };
    }

    public function resolveTemporality(Descriptor $descriptor): ?Temporality {
        return match ($this) {
            self::Delta => Temporality::Delta,
            self::Cumulative => Temporality::Cumulative,
            self::LowMemory => $descriptor->temporality,
        };
    }
}
