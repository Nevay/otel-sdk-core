<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use Nevay\OtelSDK\Common\Attributes;

final class Overflow {

    public final const INDEX = '';

    public static function check(array $indexedAttributes, int|string $index, ?int $cardinalityLimit): bool {
        return $cardinalityLimit !== null && count($indexedAttributes) >= $cardinalityLimit && !isset($indexedAttributes[$index]);
    }
    
    public static function attributes(): Attributes {
        static $attributes = new Attributes(['otel.metric.overflow' => true]);
        return $attributes;
    }
}
