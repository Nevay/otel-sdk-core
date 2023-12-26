<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

interface HighResolutionTime {

    /**
     * Returns the monotonic elapsed time from an arbitrary point in time in
     * nanoseconds.
     *
     * @return int elapsed time in nanoseconds
     */
    public function nanotime(): int;
}
