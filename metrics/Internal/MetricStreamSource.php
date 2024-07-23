<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Internal\Stream\MetricStream;
use Nevay\OTelSDK\Metrics\MeterConfig;

/**
 * @internal
 */
final class MetricStreamSource {

    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly MetricStream $stream,
        public readonly int $reader,
    ) {}
}
