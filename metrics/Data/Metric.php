<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

/**
 * @template TData
 */
final class Metric {

    /**
     * @param TData $data
     */
    public function __construct(
        public readonly Descriptor $descriptor,
        public readonly mixed $data,
    ) {}
}
