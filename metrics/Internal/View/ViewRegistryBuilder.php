<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class ViewRegistryBuilder {

    /** @var WildcardPatternMatcherBuilder<Selector> */
    private readonly WildcardPatternMatcherBuilder $patternMatcherBuilder;

    public function __construct() {
        $this->patternMatcherBuilder = new WildcardPatternMatcherBuilder();
    }

    public function register(
        View $view,
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ): self {
        $this->patternMatcherBuilder->add($name ?? '*', new Selector($view, $type, null, $unit, $meterName, $meterVersion, $meterSchemaUrl));

        return $this;
    }

    public function build(): ViewRegistry {
        return new CompiledViewRegistry($this->patternMatcherBuilder->build());
    }
}
