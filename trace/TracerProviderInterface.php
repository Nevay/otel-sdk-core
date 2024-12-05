<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\Configurable;
use Nevay\OTelSDK\Common\Provider;

/**
 * @implements Configurable<TracerConfig>
 */
interface TracerProviderInterface extends \OpenTelemetry\API\Trace\TracerProviderInterface, Provider, Configurable {

}
