<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use Nevay\OTelSDK\Trace\Internal;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\Sampler\Composable\ComposableSampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SamplingResult;
use OpenTelemetry\API\Trace\TraceState;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;
use function hexdec;
use function sprintf;
use function strspn;
use function substr;
use function unpack;

/**
 * @experimental
 */
final class CompositeSampler implements Sampler {

    /**
     * @param ComposableSampler $sampler composable sampler to delegate to
     * @param Randomizer $randomizer randomizer to generate an explicit randomness value if trace randomness flag
     *        is not set
     */
    public function __construct(
        private readonly ComposableSampler $sampler,
        private readonly Randomizer $randomizer = new Randomizer(new PcgOneseq128XslRr64()),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function shouldSample(SamplingParams $params): SamplingResult {
        $parent = $params->parent;

        static $emptyTraceState = new TraceState();
        $traceState = $parent->getTraceState() ?? $emptyTraceState;
        $ot = $traceState->get('ot') ?? '';

        $randomness = ($rvs = Internal\TraceStateHandler::seek($ot, 'rv')) && $rvs[1] === 14 && strspn($ot, 'ß123456789abcdef', $rvs[0], $rvs[1]) === $rvs[1]
            ? hexdec(substr($ot, $rvs[0], $rvs[1]))
            : null;

        if ($randomness === null && (~$params->traceFlags & 0x2) && !$parent->isValid()) {
            // generate randomness for root spans and set rv
            $randomness = $this->randomizer->getInt(0, (1 << 56) - 1);
            $ot = Internal\TraceStateHandler::set($ot, $rvs, 'rv', sprintf('%014x', $randomness));
        }
        if ($randomness === null && (~$params->traceFlags & 0x2)) {
            $this->logger->warning('The sampler is presuming TraceIDs are random and expects the Trace random flag to be set in confirmation. Please upgrade your caller(s) to use W3C Trace Context Level 2.');
        }

        // use implicit randomness from trace id
        $randomness ??= unpack('J', $params->traceId, 8)[1] & (1 << 56) - 1;

        $parentThreshold = ($ths = Internal\TraceStateHandler::seek($ot, 'th')) && $ths[1] >= 1 && $ths[1] <= 14 && strspn($ot, 'ß123456789abcdef', $ths[0], $ths[1]) === $ths[1]
            ? hexdec(substr($ot, $ths[0], $ths[1])) << 56 - $ths[1] * 4
            : null;

        if ($parentThreshold !== null && ($parentThreshold <= $randomness) !== $parent->isSampled()) {
            $this->logger->warning('Mismatch between sampling threshold and sampled flag detected. Ignoring sampling threshold.');
            $parentThreshold = null;
        }

        $intent = $this->sampler->getSamplingIntent(
            $params,
            $parentThreshold,
        );

        if ($intent->updateTraceState) {
            $traceState = ($intent->updateTraceState)($traceState);
        }

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
