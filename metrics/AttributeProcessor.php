<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

interface AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes;
}
