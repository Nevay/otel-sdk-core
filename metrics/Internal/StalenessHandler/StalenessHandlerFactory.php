<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

interface StalenessHandlerFactory {

    public function create(): StalenessHandler&ReferenceCounter;
}
