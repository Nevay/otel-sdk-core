<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use Closure;
use Nevay\OTelSDK\Common\Configurable;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Configurator\CallbackConfigurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use WeakMap;
use function array_pop;

/**
 * @template TConfig
 * @implements Configurable<TConfig>
 *
 * @internal
 */
final class ConfiguratorStack implements Configurable {

    /** @var Closure(): TConfig */
    private readonly Closure $factory;
    /** @var Closure(TConfig): void */
    private readonly Closure $baseConfigurator;
    /** @var Closure(TConfig): mixed */
    private readonly Closure $hash;
    /** @var list<Configurator<TConfig>> */
    private array $configurators = [];
    /** @var WeakMap<InstrumentationScope, TConfig> */
    private WeakMap $configs;

    /** @var list<Closure(TConfig, InstrumentationScope): void> */
    private array $onChange = [];

    /**
     * @param Closure(): TConfig $factory
     * @param Closure(TConfig): void $baseConfigurator
     * @param Closure(TConfig): mixed|null $hash
     */
    public function __construct(Closure $factory, Closure $baseConfigurator, ?Closure $hash = null) {
        $this->factory = $factory;
        $this->baseConfigurator = $baseConfigurator;
        $this->configs = new WeakMap();
        $this->hash = $hash ?? serialize(...);
    }

    public function __clone(): void {
        $this->configs = new WeakMap();
    }

    /**
     * Updates the configurator.
     *
     * If additional configurators were pushed prior to calling `update`, then only the last
     * configurator will be replaced.
     *
     * ```
     * $this->pop();
     * $this->push($configurator);
     * ```
     *
     * @param Configurator<TConfig>|Closure(TConfig, InstrumentationScope): void $configurator
     */
    public function updateConfigurator(Configurator|Closure $configurator): void {
        $configurator = self::wrapIfNeeded($configurator);

        /** @var Configurator<TConfig>|null $previousConfigurator */
        $previousConfigurator = array_pop($this->configurators);
        $this->configurators[] = $configurator;
        foreach ($this->configs as $instrumentationScope => $config) {
            if ($previousConfigurator?->appliesTo($instrumentationScope)) {
                $hash = ($this->hash)($config);
                $this->applyConfiguratorStack($config, $instrumentationScope);
                $this->triggerOnChange($config, $hash, $instrumentationScope);
            } elseif ($configurator->appliesTo($instrumentationScope)) {
                $hash = ($this->hash)($config);
                $configurator->update($config, $instrumentationScope);
                $this->triggerOnChange($config, $hash, $instrumentationScope);
            }
        }
    }

    /**
     * Pushes a configurator onto the stack.
     *
     * The pushed configurator will have precedence over previously registered configurators.
     *
     * @param Configurator<TConfig>|Closure(TConfig, InstrumentationScope): void $configurator
     */
    public function push(Configurator|Closure $configurator): self {
        $configurator = self::wrapIfNeeded($configurator);

        $this->configurators[] = $configurator;
        foreach ($this->configs as $instrumentationScope => $config) {
            if ($configurator->appliesTo($instrumentationScope)) {
                $hash = ($this->hash)($config);
                $configurator->update($config, $instrumentationScope);
                $this->triggerOnChange($config, $hash, $instrumentationScope);
            }
        }

        return $this;
    }

    /**
     * Pops the last configurator from the stack.
     */
    public function pop(): self {
        /** @var Configurator<TConfig>|null $previousConfigurator */
        if (!$previousConfigurator = array_pop($this->configurators)) {
            return $this;
        }

        foreach ($this->configs as $instrumentationScope => $config) {
            if ($previousConfigurator->appliesTo($instrumentationScope)) {
                $hash = ($this->hash)($config);
                $this->applyConfiguratorStack($config, $instrumentationScope);
                $this->triggerOnChange($config, $hash, $instrumentationScope);
            }
        }

        return $this;
    }

    /**
     * @param Configurator<TConfig>|Closure(TConfig, InstrumentationScope): void $configurator
     * @return Configurator<TConfig>
     */
    private static function wrapIfNeeded(Configurator|Closure $configurator): Configurator {
        if ($configurator instanceof Closure) {
            $configurator = new CallbackConfigurator($configurator);
        }

        return $configurator;
    }

    /**
     * @return TConfig configuration for the given instrumentation scope
     */
    public function resolveConfig(InstrumentationScope $instrumentationScope): mixed {
        if ($config = $this->configs[$instrumentationScope] ?? null) {
            return $config;
        }

        $config = clone ($this->factory)($instrumentationScope);
        $this->applyConfiguratorStack($config, $instrumentationScope);

        return $this->configs[$instrumentationScope] = $config;
    }

    private function applyConfiguratorStack(mixed $config, InstrumentationScope $instrumentationScope): void {
        ($this->baseConfigurator)($config);
        foreach ($this->configurators as $configurator) {
            $configurator->update($config, $instrumentationScope);
        }
    }

    private function triggerOnChange(mixed $config, mixed $hash, InstrumentationScope $instrumentationScope): void {
        if (($this->hash)($config) === $hash) {
            return;
        }
        foreach ($this->onChange as $callback) {
            $callback($config, $instrumentationScope);
        }
    }

    /**
     * @param Closure(TConfig, InstrumentationScope): void $callback callback to invoke on
     *        configuration change
     */
    public function onChange(Closure $callback): void {
        $this->onChange[] = $callback;
    }
}
