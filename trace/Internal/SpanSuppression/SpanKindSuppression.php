<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Trace\SpanSuppression;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextKeyInterface;

/**
 * @implements ContextKeyInterface<array<string, true>>
 *
 * @internal
 */
enum SpanKindSuppression implements SpanSuppression, ContextKeyInterface {

    case Internal;
    case Client;
    case Server;
    case Producer;
    case Consumer;

    public function isSuppressed(ContextInterface $context): bool {
        return $context->get($this) !== null;
    }

    public function suppress(ContextInterface $context): ContextInterface {
        $suppressed = $context->get($this) ?? [];
        return $context->with($this, $suppressed);
    }
}
