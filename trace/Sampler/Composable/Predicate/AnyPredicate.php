<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function implode;
use function sprintf;

/**
 * @experimental
 */
final class AnyPredicate implements Predicate {

    private readonly array $predicates;

    public function __construct(Predicate ...$predicates) {
        $this->predicates = $predicates;
    }

    public function matches(SamplingParams $params): bool {
        foreach ($this->predicates as $predicate) {
            if ($predicate->matches($params)) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string {
        return sprintf('any(%s)', implode(',', $this->predicates));
    }
}
