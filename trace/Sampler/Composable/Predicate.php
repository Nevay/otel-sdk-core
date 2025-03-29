<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

/**
 * @experimental
 */
interface Predicate {

    public function matches(SamplingParams $params): bool;

    public function __toString(): string;
}
