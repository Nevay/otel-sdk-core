<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Nevay\OTelSDK\Trace\SamplingParams;
use function implode;
use function sprintf;

/**
 * @experimental
 */
final class ComposableRuleBasedSampler implements ComposableSampler {

    private readonly array $rules;
    private readonly SamplingIntent $fallback;

    public function __construct(SamplingRule $rule, SamplingRule ...$rules) {
        $this->rules = [$rule, ...$rules];
        $this->fallback = new SamplingIntent(null, false);
    }

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
    ): SamplingIntent {
        foreach ($this->rules as $rule) {
            if (!$rule->predicate->matches($params)) {
                continue;
            }

            return $rule->sampler->getSamplingIntent(
                $params,
                $parentThreshold,
            );
        }

        return $this->fallback;
    }

    public function __toString(): string {
        return sprintf('RuleBased{%s}', implode(',', array_map(static fn(SamplingRule $rule) => sprintf('rule(%s)=%s', $rule->predicate, $rule->sampler), $this->rules)));
    }
}
