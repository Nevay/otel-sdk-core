<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;

final class TraceBasedFilter implements ExemplarFilter {

    public function accepts(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): bool {
        return Span::fromContext($context)->getContext()->isSampled();
    }
}
