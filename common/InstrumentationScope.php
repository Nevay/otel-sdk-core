<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

final class InstrumentationScope {

    public function __construct(
        public readonly string $name,
        public readonly ?string $version,
        public readonly ?string $schemaUrl,
        public readonly Attributes $attributes,
    ) {}
}
