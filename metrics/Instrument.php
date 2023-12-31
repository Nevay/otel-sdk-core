<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

final class Instrument {

    public function __construct(
        public readonly InstrumentType $type,
        public readonly string $name,
        public readonly ?string $unit,
        public readonly ?string $description,
        public readonly array $advisory = [],
    ) {}

    public function equals(Instrument $other): bool {
        return $this->type === $other->type
            && $this->name === $other->name
            && $this->unit === $other->unit
            && $this->description === $other->description
            && $this->advisory === $other->advisory;
    }
}
