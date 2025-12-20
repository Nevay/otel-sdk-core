<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcher;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;
use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function is_array;
use function sprintf;
use function substr;

/**
 * @experimental
 */
final class AttributePatternsPredicate implements Predicate {

    private readonly string $key;
    private readonly string $s;
    private readonly WildcardPatternMatcher $included;
    private readonly WildcardPatternMatcher $excluded;

    public function __construct(string $key, array|string $included = '*', array|string $excluded = []) {
        $includedBuilder = new WildcardPatternMatcherBuilder();
        $excludedBuilder = new WildcardPatternMatcherBuilder();
        $s = '';
        foreach ((array) $included as $pattern) {
            $includedBuilder->add($pattern, true);
            $s .= $pattern;
            $s .= ',';
        }
        foreach ((array) $excluded as $pattern) {
            $excludedBuilder->add($pattern, true);
            $s .= '!';
            $s .= $pattern;
            $s .= ',';
        }

        $this->key = $key;
        $this->included = $includedBuilder->build();
        $this->excluded = $excludedBuilder->build();
        $this->s = substr($s, 0, -1);
    }

    public function matches(SamplingParams $params): bool {
        $attribute = $params->attributes->get($this->key);
        if ($attribute === null) {
            return false;
        }

        return $this->_matches($attribute);
    }

    private function _matches(mixed $attribute): bool {
        if (is_array($attribute)) {
            foreach ($attribute as $value) {
                if ($this->_matches($value)) {
                    return true;
                }
            }

            return false;
        }

        $attribute = (string) $attribute;

        return $this->included->matches($attribute) && !$this->excluded->matches($attribute);
    }

    public function __toString(): string {
        return sprintf('Span.Attributes[%s]~=[%s]', $this->key, $this->s);
    }
}
