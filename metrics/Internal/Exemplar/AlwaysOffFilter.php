<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class AlwaysOffFilter implements ExemplarFilter {

    public function accepts(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): bool {
        return false;
    }
}
