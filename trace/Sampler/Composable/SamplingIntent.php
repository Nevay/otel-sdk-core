<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use Closure;
use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\TraceStateInterface;
use function assert;
use function bin2hex;
use function pack;
use function rtrim;
use function substr;

/**
 * @psalm-import-type AttributesValues from Attributes
 *
 * @experimental
 */
final class SamplingIntent {

    private ?string $th = null;

    /**
     * @param int|null $threshold 56-bit sampling threshold
     * @param iterable<string, AttributesValues> $attributes
     * @param Closure(TraceStateInterface): TraceStateInterface|null $updateTraceState
     */
    public function __construct(
        public readonly ?int $threshold,
        public readonly bool $thresholdReliable,
        public readonly iterable $attributes = [],
        public readonly ?Closure $updateTraceState = null,
    ) {}

    /**
     * @internal
     */
    public function th(): string {
        assert($this->threshold !== null && $this->thresholdReliable);
        return $this->th ??= rtrim(bin2hex(substr(pack('J', $this->threshold), 1)), '0') ?: '0';
    }
}
