<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Closure;
use Nevay\OTelSDK\Common\Provider;

interface TracerProviderInterface extends \OpenTelemetry\API\Trace\TracerProviderInterface, Provider {

    /**
     * @param Closure(TracerState $state): void $update
     */
    public function update(Closure $update): void;
}
