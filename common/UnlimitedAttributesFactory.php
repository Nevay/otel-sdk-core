<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

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

    public function builder(): AttributesBuilder {
        return new AttributesLimitingBuilder();
    }
}
