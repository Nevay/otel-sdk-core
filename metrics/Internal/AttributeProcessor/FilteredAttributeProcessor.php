<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\AttributeProcessor;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class FilteredAttributeProcessor implements AttributeProcessor {

    public function __construct(
        private readonly Closure $attributeKeys,
    ) {}

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        $raw = $attributes->toArray();
        foreach ($raw as $key => $_) {
            if (!($this->attributeKeys)($key)) {
                unset($raw[$key]);
            }
        }

        return new Attributes($raw, $attributes->getDroppedAttributesCount());
    }
}
