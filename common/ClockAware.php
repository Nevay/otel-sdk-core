<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

interface ClockAware {

    public function getClock(): Clock;
}
