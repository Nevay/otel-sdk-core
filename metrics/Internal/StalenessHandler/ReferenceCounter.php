<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\StalenessHandler;

interface ReferenceCounter {

    public function acquire(bool $persistent = false): void;

    public function release(): void;
}
