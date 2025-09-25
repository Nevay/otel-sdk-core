<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Closure;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;

/**
 * Builds a configurator that applies callbacks based on a given selection criteria.
 *
 * @template TConfig
 *
 * @experimental
 */
final class RuleConfiguratorBuilder {

    /** @var WildcardPatternMatcherBuilder<RuleConfiguratorRule<TConfig>> */
    private readonly WildcardPatternMatcherBuilder $patternMatcherBuilder;

    /** @var int<-1, max> */
    private int $order = -1;

    public function __construct() {
        $this->patternMatcherBuilder = new WildcardPatternMatcherBuilder();
    }

    /**
     * Adds a configurator rule.
     *
     * Rules are applied in registration order, with later rules taking precedence. This default
     * order can be overridden by setting a manual `$priority`.
     *
     * @param Closure(TConfig, InstrumentationScope): void $configurator configurator to apply to the configuration
     * @param string|null $name instrumentation scope name to match, supports wildcard patterns
     * @param string|null $version instrumentation scope version to match
     * @param string|null $schemaUrl instrumentation scope schema url to match
     * @param Closure(InstrumentationScope): bool|null $filter additional arbitrary filter to match
     * @param int $priority priority of this rule, rules with higher priority take precedence
     */
    public function withRule(Closure $configurator, ?string $name = null, ?string $version = null, ?string $schemaUrl = null, ?Closure $filter = null, int $priority = 0): self {
        $this->patternMatcherBuilder->add($name ?? '*', new RuleConfiguratorRule($priority, ++$this->order, $configurator, $version, $schemaUrl, $filter));

        return $this;
    }

    /**
     * @return Configurator<TConfig>
     */
    public function toConfigurator(): Configurator {
        if ($this->order === -1) {
            return new NoopConfigurator();
        }

        return new RuleConfigurator($this->patternMatcherBuilder->build());
    }
}
