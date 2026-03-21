<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Closure;
use Nevay\OTelSDK\Common\Provider;

interface LoggerProviderInterface extends \OpenTelemetry\API\Logs\LoggerProviderInterface, Provider {

    /**
     * @param Closure(LoggerState): void $update
     */
    public function update(Closure $update): void;
}
