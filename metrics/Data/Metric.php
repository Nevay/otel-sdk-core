<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Data;

/**
 * @template TData of Data
 */
final class Metric {

    /**
     * @param TData $data
     */
    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly Data $data,
    ) {}
}
