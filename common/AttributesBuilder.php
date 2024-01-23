<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * A mutable {@link Attributes} builder.
 *
 * @psalm-import-type AttributeValue from Attributes
 */
interface AttributesBuilder {

    /**
     * Adds an attribute to this builder.
     *
     * @param non-empty-string $key attribute key to set
     * @param AttributeValue $value attribute value to set
     * @return AttributesBuilder this builder
     *
     * @see https://opentelemetry.io/docs/specs/otel/common/attribute-naming/
     */
    public function add(string $key, mixed $value): AttributesBuilder;

    /**
     * Adds the given attributes.
     *
     * ```php
     * foreach ($attributes as $key => $value) {
     *     $this->add($key, $value);
     * }
     * ```
     * @param iterable<non-empty-string, AttributeValue> $attributes attributes
     *        to add
     * @return AttributesBuilder this builder
     */
    public function addAll(iterable $attributes): AttributesBuilder;

    /**
     * Removes the attribute with the given key.
     *
     * @param non-empty-string $key attribute key to remove
     * @return AttributesBuilder this builder
     */
    public function remove(string $key): AttributesBuilder;

    /**
     * Constructs immutable attributes from this builder.
     *
     * @return Attributes attributes holding the current key-value pairs
     */
    public function build(): Attributes;
}
