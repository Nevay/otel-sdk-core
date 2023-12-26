<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

final class MonotonicClock implements Clock {

    private function __construct(
        private readonly int $anchor,
        private readonly int $baseline,
        private readonly HighResolutionTime $highResolutionTime,
    ) {}

    public static function anchor(Clock $clock, HighResolutionTime $highResolutionTime): Clock {
        if ($clock instanceof self && $clock->highResolutionTime === $highResolutionTime) {
            return $clock;
        }

        return new self($clock->now(), $highResolutionTime->nanotime(), $highResolutionTime);
    }

    public function now(): int {
        return $this->highResolutionTime->nanotime() - $this->baseline + $this->anchor;
    }
}
