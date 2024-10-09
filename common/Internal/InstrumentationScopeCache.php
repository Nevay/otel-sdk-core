<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;
use WeakMap;
use WeakReference;
use function array_diff_assoc;
use function spl_object_id;

/**
 * @internal
 */
final class InstrumentationScopeCache {

    /** @var array<string, array<int, WeakReference<InstrumentationScope>>> */
    private array $instrumentationScopes = [];
    /**
     * @var WeakMap<InstrumentationScope, object>
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private readonly WeakMap $destructors;
    private readonly Closure $destructorFunction;

    public function __construct() {
        $this->destructors = new WeakMap();

        $instrumentationScopes = &$this->instrumentationScopes;
        $this->destructorFunction = static function(string $index, int $id) use (&$instrumentationScopes): void {
            unset($instrumentationScopes[$index][$id]);
            if (!$instrumentationScopes[$index]) {
                unset($instrumentationScopes[$index]);
            }
        };
    }

    public function intern(InstrumentationScope $instrumentationScope): InstrumentationScope {
        $index = $instrumentationScope->name;

        foreach ($this->instrumentationScopes[$index] ?? [] as $reference) {
            if (($internScope = $reference->get()) && self::equals($internScope, $instrumentationScope)) {
                return $internScope;
            }
        }

        $id = spl_object_id($instrumentationScope);
        /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
        $this->destructors[$instrumentationScope] = $this->destructor($this->destructorFunction, $index, $id);
        $this->instrumentationScopes[$index][$id] = WeakReference::create($instrumentationScope);

        return $instrumentationScope;
    }

    private static function equals(InstrumentationScope $left, InstrumentationScope $right): bool {
        return $left->name === $right->name
            && $left->version === $right->version
            && $left->schemaUrl === $right->schemaUrl
            && $left->attributes->count() === $right->attributes->count()
            && !array_diff_assoc($left->attributes->toArray(), $right->attributes->toArray())
        ;
    }

    private function destructor(Closure $destructor, mixed ...$args): object {
        return new class($destructor, $args) {
            public function __construct(
                private readonly Closure $destructor,
                private readonly array $args,
            ) {}
            public function __destruct() {
                ($this->destructor)(...$this->args);
            }
        };
    }
}
