<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Exemplars;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\EmptyExemplars;

/**
 * @template TSummary
 *
 * @internal
 */
final class MetricPoint {

    /**
     * @param TSummary $summary
     */
    public function __construct(
        public readonly Attributes $attributes,
        public mixed $summary,
        public Exemplars $exemplars = EmptyExemplars::Instance,
    ) {}
}
