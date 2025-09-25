<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Configurator;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;

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
        private readonly ?string $version = null,
        private readonly ?string $schemaUrl = null,
        private readonly ?Closure $filter = null,
    ) {}

    public function matches(InstrumentationScope $instrumentationScope): bool {
        return ($this->version === null || $this->version === $instrumentationScope->version)
            && ($this->schemaUrl === null || $this->schemaUrl === $instrumentationScope->schemaUrl)
            && ($this->filter === null || ($this->filter)($instrumentationScope));
    }
}
