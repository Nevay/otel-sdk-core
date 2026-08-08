<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

final class EntityRef {

    /**
     * @param non-empty-string $type
     * @param non-empty-list<non-empty-string> $identity
     * @param list<non-empty-string> $description
     * @param non-empty-string|null $schemaUrl
     */
    public function __construct(
        public readonly string $type,
        public readonly array $identity,
        public readonly array $description = [],
        public readonly ?string $schemaUrl = null,
    ) {}
}
