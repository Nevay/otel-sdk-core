<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Closure;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;

/**
 * An {@link AttributesFactory} that applies attributes limits.
 *
 * @see https://opentelemetry.io/docs/specs/otel/common/#attribute-limits
 *
 * @psalm-type AttributeKeyFilter = Closure(string): bool
 * @psalm-type AttributeValueFilter = Closure(mixed, string): bool
 */
final class AttributesLimitingFactory implements AttributesFactory {

    /**
     * @param AttributeKeyFilter|null $attributeKeyFilter
     * @param AttributeValueFilter|null $attributeValueFilter
     */
    private function __construct(
        private readonly ?int $attributeCountLimit,
        private readonly ?int $attributeValueLengthLimit,
        private readonly ?Closure $attributeKeyFilter,
        private readonly ?Closure $attributeValueFilter,
    ) {}

    /**
     * Creates a new attributes factory that applies the given limits.
     *
     * @param int|null $attributeCountLimit maximum number of attributes,
     *        attributes exceeding this limit will be dropped
     * @param int|null $attributeValueLengthLimit maximum length of string
     *        valued attributes, values exceeding this limit will be truncated
     * @param AttributeKeyFilter|null $attributeKeyFilter filter callback,
     *        attribute keys that do not pass this filter will be dropped
     * @param AttributeValueFilter|null $attributeValueFilter filter callback,
     *        attribute values that do not pass this filter will be dropped
     */
    public static function create(
        ?int $attributeCountLimit = 128,
        ?int $attributeValueLengthLimit = null,
        ?Closure $attributeKeyFilter = null,
        ?Closure $attributeValueFilter = null,
    ): AttributesFactory {
        return new self($attributeCountLimit, $attributeValueLengthLimit, $attributeKeyFilter, $attributeValueFilter);
    }

    /**
     * Filters based on an include and exclude list.
     *
     * The exclude list takes precedence over the include list.
     *
     * Wildcard patterns may use the following special characters:
     * - `?` matches any single character
     * - `*` matches any number of any characters including none
     *
     * @param list<string>|string|null $include list of attribute key patterns to include
     * @param list<string>|string|null $exclude list of attribute key patterns to exclude
     * @return AttributeKeyFilter filter callback
     */
    public static function filterKeys(array|string|null $include = null, array|string|null $exclude = null): Closure {
        $patternMatcherBuilder = new WildcardPatternMatcherBuilder();
        foreach ((array) $include as $key) {
            $patternMatcherBuilder->add($key, 1);
        }
        foreach ((array) $exclude as $key) {
            $patternMatcherBuilder->add($key, -1);
        }
        $patternMatcher = $patternMatcherBuilder->build();
        $threshold = $include === null ? 0 : 1;

        return static function(string $key) use ($patternMatcher, $threshold): bool {
            $r = 0;
            foreach ($patternMatcher->match($key) as $state) {
                $r |= $state;
            }

            return $r >= $threshold;
        };
    }

    public function build(iterable $attributes): Attributes {
        return $this->builder()->addAll($attributes)->build();
    }

    public function builder(): AttributesBuilder {
        return new AttributesLimitingBuilder(
            $this->attributeCountLimit,
            $this->attributeValueLengthLimit,
            $this->attributeKeyFilter,
            $this->attributeValueFilter,
        );
    }
}
