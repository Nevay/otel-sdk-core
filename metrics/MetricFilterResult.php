<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

/**
 * @experimental
 */
enum MetricFilterResult {

    case Accept;
    case AcceptPartial;
    case Drop;
}
