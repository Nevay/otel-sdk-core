<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

/**
 * @experimental
 */
final class TracerConfig {

    public function __construct(
        public bool $disabled = false,
    ) {}
}
