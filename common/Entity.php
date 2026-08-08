<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * @psalm-import-type AttributeValue from Attributes
 *
 * @experimental
 */
final class Entity {

    /**
     * @param non-empty-string $type
     * @param non-empty-array<non-empty-string|int, AttributeValue> $identity
     * @param array<non-empty-string|int, AttributeValue> $description
     * @param non-empty-string|null $schemaUrl
     */
    public function __construct(
        public readonly string $type,
        public readonly array $identity,
        public array $description = [],
        public readonly ?string $schemaUrl = null,
    ) {}
}
