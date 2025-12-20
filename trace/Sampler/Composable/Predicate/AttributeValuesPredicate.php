<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use function in_array;
use function is_array;
use function sprintf;

/**
 * @experimental
 */
final class AttributeValuesPredicate implements Predicate {

    public function __construct(
        private readonly string $key,
        private readonly array $values,
    ) {}

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

        return in_array($attribute, $this->values, true);
    }

    public function __toString(): string {
        return sprintf('Span.Attributes[%s]==[%s]', $this->key, implode(',', $this->values));
    }
}
