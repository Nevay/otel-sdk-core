<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

use Nevay\OTelSDK\Common\Resource;

/**
 * @template TData of Data
 */
final class Metric {

    /**
     * @param TData $data
     */
    public function __construct(
        public readonly Resource $resource,
        public readonly Descriptor $descriptor,
        public readonly Data $data,
    ) {}
}
