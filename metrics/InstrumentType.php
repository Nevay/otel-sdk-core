<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

enum InstrumentType {

    case Counter;
    case UpDownCounter;
    case Histogram;
    case Gauge;

    case AsynchronousCounter;
    case AsynchronousUpDownCounter;
    case AsynchronousGauge;
}
