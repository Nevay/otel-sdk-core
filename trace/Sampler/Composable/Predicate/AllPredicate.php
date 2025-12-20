<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function implode;
use function sprintf;

/**
 * @experimental
 */
final class AllPredicate implements Predicate {

    private readonly array $predicates;

    public function __construct(Predicate ...$predicates) {
        $this->predicates = $predicates;
    }

    public function matches(SamplingParams $params): bool {
        foreach ($this->predicates as $predicate) {
            if (!$predicate->matches($params)) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string {
        return sprintf('all(%s)', implode(',', $this->predicates));
    }
}
