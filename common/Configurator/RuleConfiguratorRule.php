<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;
use function assert;
use function preg_match;
use function preg_quote;
use function sprintf;

/**
 * @template TConfig
 *
 * @internal
 */
final class RuleConfiguratorRule {

    /**
     * @param Closure(TConfig, InstrumentationScope): void $configurator
     * @param Closure(InstrumentationScope): bool|null $filter
     *
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    public function __construct(
        private readonly int $priority,
        private readonly int $order,
        public readonly Closure $configurator,
        private readonly ?string $name = null,
        private readonly ?string $version = null,
        private readonly ?string $schemaUrl = null,
        private readonly ?Closure $filter = null,
    ) {}

    public function matches(InstrumentationScope $instrumentationScope): bool {
        assert($this->name === null || preg_match(sprintf('/^%s$/', strtr(preg_quote($this->name, '/'), ['\\?' => '.', '\\*' => '.*'])), $instrumentationScope->name));

        return ($this->version === null || $this->version === $instrumentationScope->version)
            && ($this->schemaUrl === null || $this->schemaUrl === $instrumentationScope->schemaUrl)
            && ($this->filter === null || ($this->filter)($instrumentationScope));
    }
}
