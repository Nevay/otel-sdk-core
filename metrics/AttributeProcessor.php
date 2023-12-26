<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

interface AttributeProcessor {

    public function process(Attributes $attributes, ContextInterface $context): Attributes;
}
