<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

use Closure;

interface StalenessHandler {

    public function onStale(Closure $callback): void;
}
