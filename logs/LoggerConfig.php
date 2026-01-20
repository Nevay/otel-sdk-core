<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

/**
 * @experimental
 */
final class LoggerConfig {

    public function __construct(
        public bool $enabled = true,
        public int $minimumSeverity = 0,
        public bool $traceBased = false,
    ) {}
}
