<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;

/**
 * @template TSummary
 *
 * @internal
 */
final class MetricPoint {

    /**
     * @param TSummary $summary
     * @param array<Exemplar> $exemplars
     */
    public function __construct(
        public readonly Attributes $attributes,
        public mixed $summary,
        public array $exemplars = [],
    ) {}
}
