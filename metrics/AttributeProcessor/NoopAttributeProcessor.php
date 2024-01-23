<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\AttributeProcessor;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\AttributeProcessor;
use OpenTelemetry\Context\ContextInterface;

final class NoopAttributeProcessor implements AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        return $attributes;
    }
}
