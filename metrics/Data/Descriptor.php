<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\InstrumentType;

final class Descriptor {

    public function __construct(
        public readonly InstrumentationScope $instrumentationScope,
        public readonly string $name,
        public readonly ?string $unit,
        public readonly ?string $description,
        public readonly InstrumentType $instrumentType,
    ) {}
}
