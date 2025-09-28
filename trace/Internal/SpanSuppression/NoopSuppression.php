<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Trace\SpanSuppression;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
enum NoopSuppression implements SpanSuppression {

    case Instance;

    public function isSuppressed(ContextInterface $context): bool {
        return false;
    }

    public function suppress(ContextInterface $context): ContextInterface {
        return $context;
    }
}
