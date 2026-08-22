<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Closure;
use function array_key_exists;
use function count;
use function is_array;
use function is_string;
use function substr;
use const PHP_INT_MAX;

/**
 * @internal
 *
 * @psalm-import-type AttributeKeyFilter from AttributesLimitingFactory
 * @psalm-import-type AttributeValueFilter from AttributesLimitingFactory
 */
final class AttributesLimitingBuilder implements AttributesBuilder {

    private array $attributes = [];
    private int $droppedAttributesCount = 0;

    /**
     * @param AttributeKeyFilter|null $attributeKeyFilter
     * @param AttributeValueFilter|null $attributeValueFilter
     */
    public function __construct(
        private readonly ?int $attributeCountLimit = null,
        private readonly ?int $attributeValueLengthLimit = null,
        private readonly ?int $attributeValueDepthLimit = null,
        private readonly ?Closure $attributeKeyFilter = null,
        private readonly ?Closure $attributeValueFilter = null,
    ) {}

    public function build(): Attributes {
        if (!$this->attributes && !$this->droppedAttributesCount) {
            static $empty = new Attributes([]);
            return $empty;
        }

        return new Attributes($this->attributes, $this->droppedAttributesCount);
    }

    public function add(string $key, mixed $value): AttributesBuilder {
        if ($value === null) {
            unset($this->attributes[$key]);
            return $this;
        }
        if (count($this->attributes) === $this->attributeCountLimit && !array_key_exists($key, $this->attributes)) {
            $this->droppedAttributesCount++;
            return $this;
        }
        if ($this->attributeKeyFilter?->__invoke($key) === false) {
            $this->droppedAttributesCount++;
            return $this;
        }
        if ($this->attributeValueFilter?->__invoke($value, $key) === false) {
            $this->droppedAttributesCount++;
            return $this;
        }

        if ($this->attributeValueLengthLimit !== null || $this->attributeValueDepthLimit !== null) {
             $value = self::normalize(
                value: $value,
                attributeValueLengthLimit: $this->attributeValueLengthLimit ?? PHP_INT_MAX,
                attributeValueDepthLimit: $this->attributeValueDepthLimit ?? PHP_INT_MAX,
            );
        }
        $this->attributes[$key] = $value;

        return $this;
    }

    public function addAll(iterable $attributes): AttributesBuilder {
        if ($attributes instanceof Attributes) {
            $attributes = $attributes->toArray();
        }

        foreach ($attributes as $key => $value) {
            $this->add((string) $key, $value);
        }

        return $this;
    }

    public function remove(string $key): AttributesBuilder {
        unset($this->attributes[$key]);

        return $this;
    }

    private static function normalize(mixed $value, int $attributeValueLengthLimit, int $attributeValueDepthLimit): mixed {
        if (is_array($value)) {
            if ($attributeValueDepthLimit === 0) {
                return [];
            }
            foreach ($value as $k => $v) {
                $processed = self::normalize($v, $attributeValueLengthLimit, $attributeValueDepthLimit - 1);
                if ($processed !== $v) {
                    $value[$k] = $processed;
                }
            }
        }
        if (is_string($value)) {
            $value = substr($value, 0, $attributeValueLengthLimit);
        }

        return $value;
    }
}
