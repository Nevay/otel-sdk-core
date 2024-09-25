<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcher;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class CompiledViewRegistry implements ViewRegistry {

    /**
     * @param WildcardPatternMatcher<Selector> $patternMatcher
     */
    public function __construct(
        private readonly WildcardPatternMatcher $patternMatcher,
    ) {}

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable {
        $empty = true;
        foreach ($this->patternMatcher->match($instrument->name) as $selector) {
            if ($selector->accepts($instrument, $instrumentationScope)) {
                $empty = false;
                yield $selector->view;
            }
        }

        if ($empty) {
            yield new View();
        }
    }
}
