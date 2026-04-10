<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcher;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;
use function sort;

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
        $selectors = [];
        foreach ($this->patternMatcher->match($instrument->name) as $selector) {
            if ($selector->accepts($instrument, $instrumentationScope)) {
                $selectors[] = $selector;
            }
        }

        sort($selectors);

        foreach ($selectors as $selector) {
            yield $selector->view;
        }
        if (!$selectors) {
            yield new View();
        }
    }
}
