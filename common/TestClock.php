<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * @experimental
 */
final class TestClock implements Clock, HighResolutionTime {

    public function __construct(
        public int $now = 0,
    ) {}

    public function now(): int {
        return $this->now;
    }

    public function nanotime(): int {
        return $this->now;
    }
}
