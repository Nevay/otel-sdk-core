<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

/**
 * @internal
 */
final class DefaultAttributeProcessor implements AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        return $attributes;
    }

    public function uniqueIdentifier(Attributes $attributes, ContextInterface $context): string {
        return $attributes->count() ? serialize($attributes->toArray()) : '';
    }
}
