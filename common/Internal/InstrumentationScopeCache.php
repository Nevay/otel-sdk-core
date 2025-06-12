<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use Nevay\OTelSDK\Common\InstrumentationScope;
use WeakMap;
use WeakReference;
use function array_diff_assoc;
use function register_shutdown_function;
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

    public function __construct() {
        $this->destructors = new WeakMap();
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
        $this->destructors[$instrumentationScope] = $this->destructor($this->instrumentationScopes, $index, $id);
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

    private function destructor(array &$instrumentationScopes, string $index, int $id): object {
        return new class($instrumentationScopes, $index, $id) {

            private static ?bool $isShutdown = null;

            public function __construct(
                private array &$instrumentationScopes,
                private readonly string $index,
                private readonly int $id,
            ) {
                if (self::$isShutdown === null) {
                    self::$isShutdown = false;
                    register_shutdown_function(static fn() => self::$isShutdown = true);
                }
            }

            public function __destruct() {
                if (self::$isShutdown) {
                    return;
                }
                unset($this->instrumentationScopes[$this->index][$this->id]);
                if (!$this->instrumentationScopes[$this->index]) {
                    unset($this->instrumentationScopes[$this->index]);
                }
            }
        };
    }
}
