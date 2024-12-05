<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\Configurable;
use Nevay\OTelSDK\Common\Provider;

/**
 * @implements Configurable<LoggerConfig>
 */
interface LoggerProviderInterface extends \OpenTelemetry\API\Logs\LoggerProviderInterface, Provider, Configurable {

}
