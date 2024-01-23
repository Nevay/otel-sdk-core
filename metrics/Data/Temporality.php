<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

enum Temporality {

    case Delta;
    case Cumulative;
}
