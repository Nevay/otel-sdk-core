<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal;

use Nevay\OtelSDK\Metrics\Data\Descriptor;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricStream;

final class MetricStreamSource {

    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly MetricStream $stream,
        public readonly int $reader,
    ) {}
}
