<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Aggregation;

/**
 * @internal
 */
final class LastValueSummary {

    public function __construct(
        public float|int $value,
        public int $timestamp,
    ) {}
}
