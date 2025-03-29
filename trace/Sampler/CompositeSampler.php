<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Internal;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\Sampler\Composable\ComposableSampler;
use Nevay\OTelSDK\Trace\Sampler\Composable\SamplingParams;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingResult;
use Nevay\OTelSDK\Trace\Span\Kind;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\Context\ContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function hexdec;
use function strspn;
use function substr;
use function unpack;

/**
 * @experimental
 */
final class CompositeSampler implements Sampler {

    public function __construct(
        private readonly ComposableSampler $sampler,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult {
        $parent = Span::fromContext($context)->getContext();

        static $emptyTraceState = new TraceState();
        $traceState = $parent->getTraceState() ?? $emptyTraceState;
        $ot = $traceState->get('ot') ?? '';

        $randomness = ($rvs = Internal\TraceStateHandler::seek($ot, 'rv')) && $rvs[1] === 14 && strspn($ot, 'ß123456789abcdef', $rvs[0], $rvs[1]) === $rvs[1]
            ? hexdec(substr($ot, $rvs[0], $rvs[1]))
            : null;
        $parentThreshold = ($ths = Internal\TraceStateHandler::seek($ot, 'th')) && $ths[1] >= 1 && $ths[1] <= 14 && strspn($ot, 'ß123456789abcdef', $ths[0], $ths[1]) === $ths[1]
            ? hexdec(substr($ot, $ths[0], $ths[1])) << 56 - $ths[1] * 4
            : null;

        if ($randomness === null && $parent->isValid() && (~$parent->getTraceFlags() & 0x2)) {
            $this->logger->warning('The sampler is presuming TraceIDs are random and expects the Trace random flag to be set in confirmation. Please upgrade your caller(s) to use W3C Trace Context Level 2.');
        }
        $randomness ??= unpack('J', $traceId, 8)[1] & (1 << 56) - 1;

        if ($parentThreshold !== null && ($parentThreshold <= $randomness) === $parent->isSampled()) {
            $parentThresholdReliable = true;
        } elseif ($parent->isSampled()) {
            $parentThreshold = 0;
            $parentThresholdReliable = false;
        } else {
            $parentThreshold = null;
            $parentThresholdReliable = false;
        }

        $intent = $this->sampler->getSamplingIntent(
            new SamplingParams(
                $context,
                $traceId,
                $spanName,
                $spanKind,
                $attributes,
                $links,
            ),
            $parentThreshold,
            $parentThresholdReliable,
        );

        $ot = $intent->threshold !== null && $intent->thresholdReliable
            ? Internal\TraceStateHandler::set($ot, $ths, 'th', $intent->th(), true, $this->logger)
            : Internal\TraceStateHandler::unset($ot, $ths, 'th');

        $traceState = $ot !== ''
            ? $traceState->with('ot', $ot)
            : $traceState->without('ot');

        return new Internal\SamplingResult(
            decision: $intent->threshold <= $randomness
                ? SamplingDecision::RecordAndSample
                : SamplingDecision::Drop,
            traceState: $traceState,
            additionalAttributes: $intent->attributes,
        );
    }

    public function __toString(): string {
        return sprintf('CompositeSampler{sampler=%s}', $this->sampler);
    }
}
