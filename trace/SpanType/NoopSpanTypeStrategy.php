<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanType;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\Internal\SpanTypeResolver\NoopSpanTypeResolver;
use Nevay\OTelSDK\Trace\SpanTypeResolver;
use Nevay\OTelSDK\Trace\SpanTypeStrategy;

/**
 * @experimental
 */
final class NoopSpanTypeStrategy implements SpanTypeStrategy {

    public function getResolver(InstrumentationScope $instrumentationScope): SpanTypeResolver {
        static $resolver = new NoopSpanTypeResolver();
        return $resolver;
    }
}
