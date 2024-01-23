<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use function preg_match;
use function preg_quote;
use function sprintf;
use function strtr;

final class Selector {

    private ?InstrumentType $type;
    private ?string $name;
    private ?string $unit;
    private ?string $meterName;
    private ?string $meterVersion;
    private ?string $meterSchemaUrl;

    public function __construct(
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ) {
        $this->type = $type;
        $this->name = self::namePattern($name);
        $this->unit = $unit;
        $this->meterName = $meterName;
        $this->meterVersion = $meterVersion;
        $this->meterSchemaUrl = $meterSchemaUrl;
    }

    public function accepts(Instrument $instrument, InstrumentationScope $instrumentationScope): bool {
        return ($this->type === null || $this->type === $instrument->type)
            && ($this->name === null || preg_match($this->name, $instrument->name))
            && ($this->unit === null || $this->unit === $instrument->unit)
            && ($this->meterName === null || $this->meterName === $instrumentationScope->name)
            && ($this->meterVersion === null || $this->meterVersion === $instrumentationScope->version)
            && ($this->meterSchemaUrl === null || $this->meterSchemaUrl === $instrumentationScope->schemaUrl);
    }

    private static function namePattern(?string $name): ?string {
        return $name !== null
            ? sprintf('/^%s$/', strtr(preg_quote($name, '/'), ['\\?' => '.', '\\*' => '.*']))
            : null;
    }
}
