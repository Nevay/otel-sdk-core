<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

interface ClockAware {

    public function getClock(): Clock;
}
