<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextKeys;
use OpenTelemetry\Context\ScopeInterface;

/**
 * @internal
 *
 * @see \OpenTelemetry\API\Trace\Span
 */
trait SpanTrait {

    public static function fromContext(ContextInterface $context): SpanInterface {
        return \OpenTelemetry\API\Trace\Span::fromContext($context);
    }

    public static function getCurrent(): SpanInterface {
        return \OpenTelemetry\API\Trace\Span::getCurrent();
    }

    public static function getInvalid(): SpanInterface {
        return \OpenTelemetry\API\Trace\Span::getInvalid();
    }

    public static function wrap(SpanContextInterface $spanContext): SpanInterface {
        return \OpenTelemetry\API\Trace\Span::wrap($spanContext);
    }

    public function activate(): ScopeInterface {
        return $this->storeInContext(Context::getCurrent())->activate();
    }

    public function storeInContext(ContextInterface $context): ContextInterface {
        if (LocalRootSpan::isLocalRoot($context)) {
            $context = LocalRootSpan::store($context, $this);
        }

        return $context->with(ContextKeys::span(), $this);
    }
}
