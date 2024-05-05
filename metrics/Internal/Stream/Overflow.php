<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use Nevay\OTelSDK\Common\Attributes;

/**
 * @internal
 */
final class Overflow {

    public final const INDEX = -1;

    public static function check(array $indexed, int|string $index, ?int $cardinalityLimit): bool {
        return $cardinalityLimit !== null
            && !isset($indexed[$index])
            && count($indexed) - isset($indexed[Overflow::INDEX]) >= $cardinalityLimit;
    }
    
    public static function attributes(): Attributes {
        static $attributes = new Attributes(['otel.metric.overflow' => true]);
        return $attributes;
    }
}
