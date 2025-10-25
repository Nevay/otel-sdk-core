<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\Provider;

interface TracerProviderInterface extends \OpenTelemetry\API\Trace\TracerProviderInterface, Provider {

}
