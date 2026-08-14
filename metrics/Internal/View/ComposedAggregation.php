<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\InstrumentType;

/**
 * @internal
 */
final class ComposedAggregation implements Aggregation {

    public function __construct(
        private readonly Aggregation $left,
        private readonly Aggregation $right,
    ) {}

    public function aggregator(InstrumentType $instrumentType, array $advisory = []): ?Aggregator {
        return $this->left->aggregator($instrumentType, $advisory)
            ?? $this->right->aggregator($instrumentType, $advisory);
    }
}
