<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function sprintf;

/**
 * @experimental
 */
final class NotPredicate implements Predicate {

    public function __construct(
        private readonly Predicate $predicate,
    ) {}

    public function matches(SamplingParams $params): bool {
        return !$this->predicate->matches($params);
    }

    public function __toString(): string {
        return sprintf('not(%s)', $this->predicate);
    }
}
