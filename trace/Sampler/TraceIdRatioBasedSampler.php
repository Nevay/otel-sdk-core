<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler;

use InvalidArgumentException;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SamplingDecision;
use Nevay\OTelSDK\Trace\SamplingResult;
use Nevay\OTelSDK\Trace\Span\Kind;
use OpenTelemetry\Context\ContextInterface;
use function assert;
use function max;
use function min;
use function pack;
use function sprintf;
use function substr;
use function substr_compare;
use function unpack;

final class TraceIdRatioBasedSampler implements Sampler {

    private readonly float $ratio;
    private readonly string $threshold;

    /**
     * @param float $ratio sample ratio, must be between 0 and 1 (inclusive)
     */
    public function __construct(float $ratio) {
        if (!($ratio >= 0 && $ratio <= 1)) {
            throw new InvalidArgumentException(sprintf('Ratio (%s) must be be between 0 and 1 (inclusive)', $ratio));
        }

        $this->ratio = $ratio;
        $this->threshold = substr(pack('J', self::computeTValue($ratio, minPrecision: 14, bitPrecision: 12)), 1);
    }

    public function shouldSample(
        ContextInterface $context,
        string $traceId,
        string $spanName,
        Kind $spanKind,
        Attributes $attributes,
        array $links,
    ): SamplingResult {
        return $this->ratio >= 2 ** -56 && substr_compare($traceId, $this->threshold, 9) >= 0
            ? SamplingDecision::RecordAndSample
            : SamplingDecision::Drop;
    }

    public function __toString(): string {
        return sprintf('TraceIdRatioBased{%F}', $this->ratio);
    }

    /**
     * Computes the 56-bit rejection threshold (T-value) for a given probability.
     *
     * The T-value is computed as `2**56*(1-$probability)` with a precision of
     * `2**-(4*Max(⌈(-log2($probability)+$bitPrecision)/4⌉,$minPrecision))`.
     *
     * Values below `2**-56` will return `0`.
     *
     * ```
     * 1/3 w/ $minPrecision=3
     * => 1 - 1/3
     * => 2/3
     * => 2730.666../4096
     * => 2731/4096
     * => 0xaab
     * ```
     *
     * Converting the result into `th` hexadecimal value:
     * ```
     * $th = rtrim(bin2hex(substr(pack('J', $t), 1)), '0') ?: '0';
     * ```
     *
     * @param float $probability sampling probability, must be between 0 and 1
     * @param int $minPrecision minimum precision in hexadecimal digits
     * @param int $bitPrecision precision increase in bits
     * @return int 56bit T-value
     */
    private static function computeTValue(float $probability, int $minPrecision = 0, int $bitPrecision = 0): int {
        assert($probability >= 0 && $probability <= 1);
        assert($minPrecision >= 0);
        assert($bitPrecision >= 0);

        $b = unpack('J', pack('E', $probability))[1];
        $e = $b >> 52 & (1 << 11) - 1;
        $f = $b & (1 << 52) - 1 | !!$e << 52;

        // 56+1bit for rounding
        $s = $e - 1023 - 52 + 57;
        $t = (1 << 57) - ($s < 0 ? $f >> -$s : $f << $s);

        // minimum precision in hexadecimal digits
        $p = -($e - 1023) + 3 + $bitPrecision >> 2;
        $p = min(14, max($minPrecision, $p));
        $m = 1 << (14 - $p << 2);

        return $t + $m >> 1 & -$m;
    }
}
