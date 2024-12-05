<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Configurable;
use Nevay\OTelSDK\Common\Provider;

/**
 * @implements Configurable<MeterConfig>
 */
interface MeterProviderInterface extends \OpenTelemetry\API\Metrics\MeterProviderInterface, Provider, Configurable {

}
