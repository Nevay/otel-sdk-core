<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use GMP;

/**
 * @template TSummary
 */
final class Delta {

    /**
     * @param Metric<TSummary> $metric
     * @param Delta<TSummary>|null $prev
     */
    public function __construct(
        public Metric $metric,
        public int|GMP $readers,
        public ?self $prev = null,
    ) {}
}
