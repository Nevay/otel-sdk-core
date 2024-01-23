<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Aggregation;

final class SumSummary {

    public function __construct(
        public float|int $value,
        public float|int $valueCompensation,
    ) {}
}
