<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Generator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class MutableViewRegistry implements ViewRegistry {

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

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): ?iterable {
        $patternMatcher = $this->patternMatcherBuilder->build();
        $views = (static function() use ($instrument, $instrumentationScope, $patternMatcher): Generator {
            foreach ($patternMatcher->match($instrument->name) as $selector) {
                if ($selector->accepts($instrument, $instrumentationScope)) {
                    yield $selector->view;
                }
            }
        })();

        return $views->valid() ? $views : null;
    }
}
