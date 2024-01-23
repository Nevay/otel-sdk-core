<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

final class MultiReferenceCounter implements ReferenceCounter {

    /**
     * @param list<ReferenceCounter> $referenceCounters
     */
    public function __construct(
        private readonly array $referenceCounters,
    ) {}

    public function acquire(bool $persistent = false): void {
        foreach ($this->referenceCounters as $referenceCounter) {
            $referenceCounter->acquire($persistent);
        }
    }

    public function release(): void {
        foreach ($this->referenceCounters as $referenceCounter) {
            $referenceCounter->release();
        }
    }
}
