<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Psr\Log\LoggerInterface;
use WeakMap;
use WeakReference;
use function hash;
use function serialize;

/**
 * @internal
 */
final class InstrumentationScopeCache {

    /** @var array<string, WeakReference<InstrumentationScope>> */
    private array $instrumentationScopes = [];
    /**
     * @var WeakMap<InstrumentationScope, object>
     * @noinspection PhpPropertyOnlyWrittenInspection
     */
    private readonly WeakMap $destructors;

    public function __construct(
        private readonly ?LoggerInterface $logger,
    ) {
        $this->destructors = new WeakMap();
    }

    public function intern(InstrumentationScope $instrumentationScope): InstrumentationScope {
        $instrumentationScopeId = hash('xxh128', serialize([
            $instrumentationScope->name,
            $instrumentationScope->version,
            $instrumentationScope->schemaUrl,
        ]), true);

        if (!$internScope = ($this->instrumentationScopes[$instrumentationScopeId] ?? null)?->get()) {
            /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
            $this->destructors[$instrumentationScope] = $this->destructor($instrumentationScopeId);
            $this->instrumentationScopes[$instrumentationScopeId] = WeakReference::create($instrumentationScope);

            return $instrumentationScope;
        }

        if ($internScope->attributes->toArray() !== $instrumentationScope->attributes->toArray()) {
            $this->logger?->warning('Instrumentation scope with same identity and differing non-identifying fields, using first-seen instrumentation scope', [
                'name' => $instrumentationScope->name,
                'version' => $instrumentationScope->version,
                'schemaUrl' => $instrumentationScope->schemaUrl,
            ]);
        }

        return $internScope;
    }

    private function destructor(string $instrumentationScopeId): object {
        return new class($this->prune(...), $instrumentationScopeId) {
            public function __construct(
                private readonly Closure $prune,
                private readonly string $instrumentationScopeId,
            ) {}
            public function __destruct() {
                ($this->prune)($this->instrumentationScopeId);
            }
        };
    }

    private function prune(string $instrumentationScopeId): void {
        unset($this->instrumentationScopes[$instrumentationScopeId]);
    }
}
