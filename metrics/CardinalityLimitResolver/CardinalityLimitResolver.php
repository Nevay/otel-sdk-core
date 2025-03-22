<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\CardinalityLimitResolver;

use Nevay\OTelSDK\Metrics\InstrumentType;

final class CardinalityLimitResolver implements \Nevay\OTelSDK\Metrics\CardinalityLimitResolver {

    public function __construct(
        private readonly ?int $default,
        private readonly ?int $counter,
        private readonly ?int $upDownCounter,
        private readonly ?int $histogram,
        private readonly ?int $gauge,
        private readonly ?int $asynchronousCounter,
        private readonly ?int $asynchronousUpDownCounter,
        private readonly ?int $asynchronousGauge,
    ) {}

    public function resolveCardinalityLimit(InstrumentType $instrumentType): ?int {
        return match ($instrumentType) {
            InstrumentType::Counter => $this->counter,
            InstrumentType::UpDownCounter => $this->upDownCounter,
            InstrumentType::Histogram => $this->histogram,
            InstrumentType::Gauge => $this->gauge,
            InstrumentType::AsynchronousCounter => $this->asynchronousCounter,
            InstrumentType::AsynchronousUpDownCounter => $this->asynchronousUpDownCounter,
            InstrumentType::AsynchronousGauge => $this->asynchronousGauge,
        } ?? $this->default;
    }
}
