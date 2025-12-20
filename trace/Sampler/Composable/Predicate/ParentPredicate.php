<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;

/**
 * @experimental
 */
final class ParentPredicate implements Predicate {

    public function __construct(
        private readonly ?bool $remote = null,
    ) {}

    public function matches(SamplingParams $params): bool {
        return $params->parent->isValid() && ($this->remote === null || $this->remote === $params->parent->isRemote());
    }

    public function __toString(): string {
        return match ($this->remote) {
            null => 'has(Span.Parent)',
            true => 'is_remote(Span.Parent)',
            false => 'is_local(Span.Parent)',
        };
    }
}
