<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

/**
 * @experimental
 */
final class MeterConfig {

    public function __construct(
        public readonly bool $disabled = false,
    ) {}
}
