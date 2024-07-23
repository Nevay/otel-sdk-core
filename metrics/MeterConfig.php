<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;

/**
 * @property-read bool $disabled
 *
 * @experimental
 */
final class MeterConfig {

    private array $onChange = [];

    public function __construct(
        public bool $disabled = false,
    ) {}

    public function setDisabled(bool $disabled): self {
        if ($disabled !== $this->disabled) {
            $this->disabled = $disabled;
            $this->triggerOnChange();
        }

        return $this;
    }

    /**
     * @param Closure(MeterConfig): void $closure
     *
     * @internal
     */
    public function onChange(Closure $closure): self {
        $this->onChange[] = $closure;

        return $this;
    }

    /**
     * @internal
     */
    public function triggerOnChange(): self {
        foreach ($this->onChange as $onChange) {
            $onChange($this);
        }

        return $this;
    }
}
