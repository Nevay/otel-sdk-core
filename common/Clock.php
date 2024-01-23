<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

interface Clock {

    /**
     * Returns the current time in nanoseconds.
     *
     * @return int current time in nanoseconds
     */
    public function now(): int;
}
