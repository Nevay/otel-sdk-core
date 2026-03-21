<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Closure;
use Nevay\OTelSDK\Common\Provider;

interface MeterProviderInterface extends \OpenTelemetry\API\Metrics\MeterProviderInterface, Provider {

    /**
     * @param Closure(MeterState): void $update
     */
    public function update(Closure $update): void;
}
