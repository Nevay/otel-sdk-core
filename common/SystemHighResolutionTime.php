<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use function hrtime;

final class SystemHighResolutionTime implements HighResolutionTime {

    private function __construct() {}

    public static function create(): HighResolutionTime {
        static $instance = new self();
        return $instance;
    }

    public function nanotime(): int {
        return hrtime(true);
    }
}
