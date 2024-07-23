<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

/**
 * @property-read bool $disabled
 *
 * @experimental
 */
final class LoggerConfig {

    public function __construct(
        public bool $disabled = false,
    ) {}

    public function setDisabled(bool $disabled): self {
        $this->disabled = $disabled;

        return $this;
    }
}
