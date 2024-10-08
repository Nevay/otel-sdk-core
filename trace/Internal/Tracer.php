<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\TracerConfig;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * @internal
 */
final class Tracer implements TracerInterface {

    public function __construct(
        private readonly TracerState $tracerState,
        private readonly InstrumentationScope $instrumentationScope,
        private readonly TracerConfig $tracerConfig,
    ) {}

    public function isEnabled(): bool {
        return !$this->tracerConfig->disabled;
    }

    public function spanBuilder(string $spanName): SpanBuilderInterface {
        return new SpanBuilder($this->tracerState, $this->instrumentationScope, $this->tracerConfig, $spanName);
    }
}
