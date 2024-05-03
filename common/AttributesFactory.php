<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * An {@link AttributesBuilder} factory.
 *
 * @psalm-import-type AttributeValue from Attributes
 */
interface AttributesFactory {

    /**
     * Returns a new attribute builder.
     *
     * @return AttributesBuilder attribute builder
     */
    public function builder(): AttributesBuilder;

    /**
     * Builds attributes containing the given key-value pairs.
     *
     * @param iterable<non-empty-string, AttributeValue> $attributes
     * @return Attributes attributes containing the given key-value pairs
     */
    public function build(iterable $attributes): Attributes;
}
