<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;

/**
 * @template TSummary
 */
final class Metric {

    /**
     * @param array<Attributes> $attributes
     * @param array<TSummary> $summaries
     * @param array<array<Exemplar>> $exemplars
     */
    public function __construct(
        public array $attributes,
        public array $summaries,
        public int $timestamp,
        public array $exemplars = [],
    ) {}
}
