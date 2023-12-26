<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

enum Temporality {

    case Delta;
    case Cumulative;
}
