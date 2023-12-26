<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Metrics\InstrumentType;

final class Descriptor {

    public function __construct(
        public readonly Resource $resource,
        public readonly InstrumentationScope $instrumentationScope,
        public readonly string $name,
        public readonly ?string $unit,
        public readonly ?string $description,
        public readonly InstrumentType $instrumentType,
        public readonly Temporality $temporality,
    ) {}
}
