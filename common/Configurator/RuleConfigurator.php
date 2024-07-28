<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcher;
use function sort;

/**
 * @template TConfig
 * @implements Configurator<TConfig>
 *
 * @internal
 */
final class RuleConfigurator implements Configurator {

    /**
     * @param WildcardPatternMatcher<RuleConfiguratorRule<TConfig>> $patternMatcher
     */
    public function __construct(
        private readonly WildcardPatternMatcher $patternMatcher,
    ) {}

    public function update(mixed $config, InstrumentationScope $instrumentationScope): bool {
        $rules = [];
        foreach ($this->patternMatcher->match($instrumentationScope->name) as $rule) {
            if ($rule->matches($instrumentationScope)) {
                $rules[] = $rule;
            }
        }
        if (!$rules) {
            return false;
        }

        sort($rules);
        foreach ($rules as $rule) {
            ($rule->configurator)($config, $instrumentationScope);
        }

        return true;
    }

    public function appliesTo(InstrumentationScope $instrumentationScope): bool {
        foreach ($this->patternMatcher->match($instrumentationScope->name) as $rule) {
            if ($rule->matches($instrumentationScope)) {
                return true;
            }
        }

        return false;
    }
}
