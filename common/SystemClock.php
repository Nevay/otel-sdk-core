<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use function microtime;

final class SystemClock implements Clock {

    private function __construct() {}

    public static function create(): Clock {
        static $instance = new self();
        return $instance;
    }

    public function now(): int {
        return (int) (microtime(true) * 1e9);
    }
}
