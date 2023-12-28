<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Data\Exemplar;

/**
 * @template TSummary
 */
final class Metric {

    /**
     * @param array<Attributes> $attributes
     * @param array<TSummary> $summaries
     * @param array<Exemplar> $exemplars
     */
    public function __construct(
        public array $attributes,
        public array $summaries,
        public int $timestamp,
        public array $exemplars = [],
    ) {}
}
