<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class Selector {

    /**
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    public function __construct(
        private readonly int $order,
        public readonly View $view,
        private readonly ?InstrumentType $type = null,
        private readonly ?string $unit = null,
        private readonly ?string $meterName = null,
        private readonly ?string $meterVersion = null,
        private readonly ?string $meterSchemaUrl = null,
    ) {}

    public function accepts(Instrument $instrument, InstrumentationScope $instrumentationScope): bool {
        return ($this->type === null || $this->type === $instrument->type)
            && ($this->unit === null || $this->unit === $instrument->unit)
            && ($this->meterName === null || $this->meterName === $instrumentationScope->name)
            && ($this->meterVersion === null || $this->meterVersion === $instrumentationScope->version)
            && ($this->meterSchemaUrl === null || $this->meterSchemaUrl === $instrumentationScope->schemaUrl);
    }
}
