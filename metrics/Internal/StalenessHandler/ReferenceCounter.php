<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

/**
 * @internal
 */
interface ReferenceCounter {

    public function acquire(bool $persistent = false): void;

    public function release(): void;
}
