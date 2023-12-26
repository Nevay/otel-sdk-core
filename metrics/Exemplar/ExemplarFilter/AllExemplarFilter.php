<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar\ExemplarFilter;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Exemplar\ExemplarFilter;
use OpenTelemetry\Context\ContextInterface;

final class AllExemplarFilter implements ExemplarFilter {

    public function accepts(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): bool {
        return true;
    }
}
