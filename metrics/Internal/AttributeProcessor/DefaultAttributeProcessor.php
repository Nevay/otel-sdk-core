<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class DefaultAttributeProcessor implements AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        return $attributes;
    }
}
