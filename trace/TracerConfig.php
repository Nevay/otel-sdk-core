<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

/**
 * @property-read bool $disabled
 *
 * @experimental
 */
final class TracerConfig {

    public function __construct(
        public bool $disabled = false,
    ) {}

    public function setDisabled(bool $disabled): self {
        $this->disabled = $disabled;

        return $this;
    }
}
