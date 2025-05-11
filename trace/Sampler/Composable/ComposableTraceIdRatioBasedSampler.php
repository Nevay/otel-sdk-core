<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use InvalidArgumentException;
use Nevay\OTelSDK\Trace\SamplingParams;
use function assert;
use function pack;
use function sprintf;
use function unpack;

/**
 * @experimental
 */
final class ComposableTraceIdRatioBasedSampler implements ComposableSampler {

    private readonly float $ratio;
    private readonly SamplingIntent $intent;

    /**
     * @param float $ratio sample ratio, must be between 0 and 1 (inclusive)
     * @param int<1, 14> $precision threshold precision in hexadecimal digits
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function __construct(float $ratio, int $precision = 4) {
        if (!($ratio >= 0 && $ratio <= 1)) {
            throw new InvalidArgumentException(sprintf('Ratio (%s) must be be between 0 and 1 (inclusive)', $ratio));
        }
        if ($precision < 1 || $precision > 14) {
            throw new InvalidArgumentException(sprintf('Precision (%d) must be between 1 and 14 (inclusive)', $precision));
        }

        $this->ratio = $ratio;
        $this->intent = $ratio >= 2 ** -56
            ? new SamplingIntent(self::computeTValue($ratio, $precision, 4), true)
            : new SamplingIntent(null, false);
    }

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
    ): SamplingIntent {
        return $this->intent;
    }

    public function __toString(): string {
        return sprintf('TraceIdRatioBased{%F}', $this->ratio);
    }

    /**
     * Computes the 56-bit rejection threshold (T-value) for a given probability.
     *
     * The T-value is computed as `2**56*(1-$probability)` with a precision of
     * `2**-($wordSize*⌈-log2($probability)/$wordSize+$precision-1⌉)`.
     *
     * Values below `2**-56` will return `0`.
     *
     * ```
     * 1/3 w/ precision=3, wordSize=4
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
     * @param positive-int $precision precision in words
     * @param positive-int $wordSize word size, must be a power of two
     * @return int 56bit T-value
     */
    private static function computeTValue(float $probability, int $precision, int $wordSize = 1): int {
        assert($probability >= 0 && $probability <= 1);
        assert($precision >= 1);
        assert($wordSize >= 1 && ($wordSize & $wordSize - 1) === 0);

        $b = unpack('J', pack('E', $probability))[1];
        $e = $b >> 52 & (1 << 11) - 1;
        $f = $b & (1 << 52) - 1 | !!$e << 52;

        // 56+1bit for rounding
        $s = $e - 1023 - 52 + 57;
        $t = (1 << 57) - ($s < 0 ? $f >> -$s : $f << $s);
        $m = -1 << 56 >> (-($e - 1023 + 1) + $precision * $wordSize & -$wordSize);

        return $t - $m >> 1 & $m;
    }
}
