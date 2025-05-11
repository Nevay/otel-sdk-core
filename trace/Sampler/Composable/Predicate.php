<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;

/**
 * @experimental
 */
interface Predicate {

    public function matches(SamplingParams $params): bool;

    public function __toString(): string;
}
