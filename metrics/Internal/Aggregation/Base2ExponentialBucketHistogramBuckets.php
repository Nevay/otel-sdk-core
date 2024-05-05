<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Aggregation;

use Nevay\OTelSDK\Metrics\Data\ExponentialHistogramBuckets;
use function max;
use function min;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * @internal
 */
final class Base2ExponentialBucketHistogramBuckets {

    public function __construct(
        public int $scale,
        public int $lo = PHP_INT_MAX,
        public int $hi = PHP_INT_MIN,
        public array $buckets = [],
    ) {}

    public function increment(int $index, int $maxSize): void {
        $this->lo = min($this->lo, $index);
        $this->hi = max($this->hi, $index);

        if ($factor = self::scaleFactor($this->lo, $this->hi, $maxSize)) {
            $this->scale -= $factor;
            $this->lo >>= $factor;
            $this->hi >>= $factor;
            $this->buckets = self::scaleBy($factor);
            $index >>= $factor;
        }

        $this->buckets[$index] ??= 0;
        $this->buckets[$index]++;
    }

    public function merge(self $other, int $maxSize, int $multiplier): self {
        $scale = min($this->scale, $other->scale);
        $thisFactor = $this->scale - $scale;
        $otherFactor = $other->scale - $scale;
        $lo = min($this->lo >> $thisFactor, $other->lo >> $otherFactor);
        $hi = max($this->hi >> $thisFactor, $other->hi >> $otherFactor);

        if ($factor = self::scaleFactor($lo, $hi, $maxSize)) {
            $scale -= $factor;
            $thisFactor += $factor;
            $otherFactor += $factor;
            $lo >>= $factor;
            $hi >>= $factor;
        }

        $merged = $this->scaleBy($thisFactor);
        foreach ($other->buckets as $index => $count) {
            $merged[$index >> $otherFactor] ??= 0;
            $merged[$index >> $otherFactor] += $count * $multiplier;
        }

        return new self($scale, $lo, $hi, $merged);
    }

    public function toData(int $scale): ?ExponentialHistogramBuckets {
        if (!$this->buckets) {
            return null;
        }

        $factor = $this->scale - $scale;

        return new ExponentialHistogramBuckets(
            $this->lo >> $factor,
            $this->hi >> $factor,
            $this->scaleBy($factor),
        );
    }

    /**
     * Returns the buckets scaled down by the given factor.
     *
     * @param int<0, max> $factor factor do scale down by
     * @return array<int, int> bucket counts
     */
    private function scaleBy(int $factor): array {
        if (!$factor) {
            return $this->buckets;
        }

        $scaled = [];
        foreach ($this->buckets as $index => $count) {
            $scaled[$index >> $factor] ??= 0;
            $scaled[$index >> $factor] += $count;
        }

        return $scaled;
    }

    private static function scaleFactor(int $lo, int $hi, int $maxSize): int {
        $factor = 0;
        while ($hi - $lo >= $maxSize || $hi >> 31 > 0 || ~$lo >> 31 > 0) {
            $lo >>= 1;
            $hi >>= 1;
            $factor++;
        }

        return $factor;
    }
}
