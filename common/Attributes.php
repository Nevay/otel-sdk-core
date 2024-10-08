<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Closure;
use Countable;
use IteratorAggregate;
use Nevay\OTelSDK\Common\Internal\WildcardPatternMatcherBuilder;
use Traversable;
use function count;

/**
 * An immutable collection of key-value pairs.
 *
 * @see https://opentelemetry.io/docs/specs/otel/common/#attribute-collections
 * @see https://opentelemetry.io/docs/specs/otel/common/#attribute
 *
 * @psalm-type AttributeValue = string|bool|float|int|list<string>|list<bool>|list<float>|list<int>
 * @implements IteratorAggregate<non-empty-string, AttributeValue>
 */
final class Attributes implements IteratorAggregate, Countable {

    /**
     * @param array<non-empty-string|int, AttributeValue> $attributes attributes entries
     * @param int<0, max> $droppedAttributesCount count of dropped attributes
     */
    public function __construct(
        private readonly array $attributes,
        private readonly int $droppedAttributesCount = 0,
    ) {}

    /**
     * Filters based on an include and exclude list.
     *
     * The exclude list takes precedence over the include list.
     *
     * Wildcard patterns may use the following special characters:
     * - `?` matches any single character
     * - `*` matches any number of any characters including none
     *
     * @param list<string>|string $include list of attribute key patterns to include
     * @param list<string>|string $exclude list of attribute key patterns to exclude
     * @return Closure(string): bool filter callback
     */
    public static function filterKeys(array|string $include = '*', array|string $exclude = []): Closure {
        $includesBuilder = new WildcardPatternMatcherBuilder();
        $excludesBuilder = new WildcardPatternMatcherBuilder();

        foreach ((array) $include as $key) {
            $includesBuilder->add($key, true);
        }
        foreach ((array) $exclude as $key) {
            $excludesBuilder->add($key, true);
        }

        $includes = $includesBuilder->build();
        $excludes = $excludesBuilder->build();

        return static fn(string $key): bool => $includes->matches($key) && !$excludes->matches($key);
    }

    /**
     * @param non-empty-string $key attribute key to check
     * @return bool true if a value for the given key exists, false otherwise
     */
    public function has(string $key): bool {
        return isset($this->attributes[$key]);
    }

    /**
     * Returns the value for the given key, or `null` if no such value exists.
     *
     * @param non-empty-string $key attribute key to retrieve
     * @return AttributeValue|null value assigned to the given key, or null if
     *         no such value exists
     */
    public function get(string $key): mixed {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Returns the count of dropped attributes.
     *
     * @return int<0, max> count of dropped attributes
     */
    public function getDroppedAttributesCount(): int {
        return $this->droppedAttributesCount;
    }

    public function count(): int {
        return count($this->attributes);
    }

    public function getIterator(): Traversable {
        foreach ($this->attributes as $key => $value) {
            yield (string) $key => $value;
        }
    }

    /**
     * @return array<non-empty-string|int, AttributeValue>
     */
    public function toArray(): array {
        return $this->attributes;
    }
}
