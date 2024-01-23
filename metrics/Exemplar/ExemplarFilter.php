<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

interface ExemplarFilter {

    public function accepts(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): bool;
}
