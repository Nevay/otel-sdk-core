<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar\ExemplarFilter;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Exemplar\ExemplarFilter;
use OpenTelemetry\Context\ContextInterface;

final class NoneExemplarFilter implements ExemplarFilter {

    public function accepts(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): bool {
        return false;
    }
}
