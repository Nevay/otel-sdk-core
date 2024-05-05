<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Metrics\Data\Descriptor;
use Nevay\OTelSDK\Metrics\Internal\Stream\MetricStream;

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
