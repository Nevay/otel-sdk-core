<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function array_filter;
use function implode;
use function sprintf;

final class ParentPredicate implements Predicate {

    public function __construct(
        private readonly ?bool $remote = null,
        private readonly ?bool $sampled = null,
    ) {}

    public function matches(SamplingParams $params): bool {
        return $params->parent->isValid()
            && ($this->remote === null || $this->remote === $params->parent->isRemote())
            && ($this->sampled === null || $this->sampled === $params->parent->isSampled())
        ;
    }

    public function __toString(): string {
        $params = array_filter([
            match ($this->remote)  { true => 'remote',  false => 'not_remote',  null => null, },
            match ($this->sampled) { true => 'sampled', false => 'not_sampled', null => null, },
        ]);

        if (!$params) {
            return 'parent';
        }

        return sprintf('parent{%s}', implode(',', $params));
    }
}
