<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Closure;

/**
 * @experimental
 */
enum CardinalityLimitResolvers implements CardinalityLimitResolver {

    case Default;
    case None;

    /**
     * @param int|null $cardinalityLimit
     * @return CardinalityLimitResolver
     */
    public static function resolved(?int $cardinalityLimit): CardinalityLimitResolver {
        return new class($cardinalityLimit) implements CardinalityLimitResolver {

            public function __construct(
                private readonly ?int $cardinalityLimit,
            ) {}

            public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
                return $this->cardinalityLimit;
            }
        };
    }

    /**
     * @param Closure(InstrumentType): ?int $callback
     * @return CardinalityLimitResolver
     */
    public static function callback(Closure $callback): CardinalityLimitResolver {
        return new class($callback) implements CardinalityLimitResolver {

            public function __construct(
                private readonly Closure $callback,
            ) {}

            public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
                return ($this->callback)($instrumentType);
            }
        };
    }

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return match ($this) {
            self::Default => 2000,
            self::None => null,
        };
    }
}
