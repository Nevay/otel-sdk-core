<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use OpenTelemetry\Context\ContextInterface;
use function serialize;

final class DefaultAttributeProcessor implements AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        return $attributes;
    }

    public function uniqueIdentifier(Attributes $attributes, ContextInterface $context): string {
        return $attributes->count() ? serialize($attributes->toArray()) : '';
    }
}
