<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

final class FilteredAttributeProcessor implements AttributeProcessor {

    public function __construct(
        private readonly array $attributeKeys,
    ) {}

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        $filtered = [];
        foreach ($this->attributeKeys as $key) {
            if (($value = $attributes->get($key)) !== null) {
                $filtered[$key] = $value;
            }
        }

        return new Attributes($filtered, 0);
    }

    public function uniqueIdentifier(Attributes $attributes, ContextInterface $context): string {
        if (!$this->attributeKeys) {
            return '';
        }

        $values = [];
        foreach ($this->attributeKeys as $key) {
            $values[] = $attributes->get($key);
        }

        return serialize($values);
    }
}
