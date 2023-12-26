<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\StalenessHandler;

interface StalenessHandlerFactory {

    public function create(): StalenessHandler&ReferenceCounter;
}
