<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\Provider;

interface LoggerProviderInterface extends \OpenTelemetry\API\Logs\LoggerProviderInterface, Provider {

}
