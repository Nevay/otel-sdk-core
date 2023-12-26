<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\AttributeProcessor;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\AttributeProcessor;
use OpenTelemetry\Context\ContextInterface;

final class NoopAttributeProcessor implements AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes {
        return $attributes;
    }
}
