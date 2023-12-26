<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

final class Instrument {

    public function __construct(
        public InstrumentType $type,
        public string $name,
        public ?string $unit,
        public ?string $description,
        public array $advisory = [],
    ) {}

    public function equals(Instrument $other): bool {
        return $this->type === $other->type
            && $this->name === $other->name
            && $this->unit === $other->unit
            && $this->description === $other->description
            && $this->advisory === $other->advisory;
    }
}
