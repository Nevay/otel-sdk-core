<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Provider;

interface MeterProviderInterface extends \OpenTelemetry\API\Metrics\MeterProviderInterface, Provider {

}
