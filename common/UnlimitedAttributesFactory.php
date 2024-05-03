<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * An {@link AttributesFactory} that does not apply attributes limits.
 *
 * @see https://opentelemetry.io/docs/specs/otel/common/#exempt-entities
 */
final class UnlimitedAttributesFactory implements AttributesFactory {

    private function __construct() {}

    public static function create(): AttributesFactory {
        static $factory = new self();
        return $factory;
    }

    public function build(iterable $attributes): Attributes {
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if ($value === null) {
                    unset($attributes[$key]);
                }
            }

            return new Attributes($attributes);
        }

        return $this->builder()->addAll($attributes)->build();
    }

    public function builder(): AttributesBuilder {
        return new AttributesLimitingBuilder();
    }
}
