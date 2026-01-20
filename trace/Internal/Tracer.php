<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\SpanSuppressor;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * @internal
 */
final class Tracer implements TracerInterface {

    public function __construct(
        public readonly TracerState $tracerState,
        public readonly InstrumentationScope $instrumentationScope,
        public bool $enabled,
        public SpanSuppressor $spanSuppressor,
    ) {}

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function spanBuilder(string $spanName): SpanBuilderInterface {
        return new SpanBuilder($this, $spanName);
    }
}
