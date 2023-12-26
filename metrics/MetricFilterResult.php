<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

/**
 * @experimental
 */
enum MetricFilterResult {

    case Accept;
    case AcceptPartial;
    case Drop;
}
