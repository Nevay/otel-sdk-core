<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use OpenTelemetry\Context\ContextInterface;

final class FilteredAttributeProcessor implements AttributeProcessor {

    public function __construct(
        private readonly array $attributeKeys,
    ) {}

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        $filtered = [];
        foreach ($this->attributeKeys as $key) {
            $filtered[$key] = $attributes->get($key);
        }

        return new Attributes($filtered, 0);
    }
}
