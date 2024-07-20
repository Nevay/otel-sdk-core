<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

/**
 * @experimental
 */
final class LoggerConfig {

    public function __construct(
        public bool $disabled = false,
    ) {}
}
