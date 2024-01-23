<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

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
    public static function fromLimit(?int $cardinalityLimit): CardinalityLimitResolver {
        return new class($cardinalityLimit) implements CardinalityLimitResolver {

            public function __construct(
                private readonly ?int $cardinalityLimit,
            ) {}

            public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
                return $this->cardinalityLimit;
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
