<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

use Countable;
use IteratorAggregate;
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
