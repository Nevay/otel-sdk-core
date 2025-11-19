<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;

final class TruePredicate implements Predicate {

    public function matches(SamplingParams $params): bool {
        return true;
    }

    public function __toString(): string {
        return 'true';
    }
}
