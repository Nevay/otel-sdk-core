<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\IdGenerator;

use Nevay\OTelSDK\Trace\IdGenerator;
use Random\Engine\PcgOneseq128XslRr64;
use Random\RandomException;
use Random\Randomizer;
use function assert;
use function mt_getrandmax;
use function mt_rand;
use function pack;
use function strspn;
use const PHP_VERSION_ID;

final class RandomIdGenerator implements IdGenerator {

    private readonly ?Randomizer $randomizer;

    /**
     * @param Randomizer|null $randomizer randomizer to use
     */
    public function __construct(?Randomizer $randomizer = null) {
        if (PHP_VERSION_ID >= 80200) {
            $randomizer ??= new Randomizer(new PcgOneseq128XslRr64());
        }

        $this->randomizer = $randomizer;
    }

    public function generateSpanIdBinary(): string {
        if ($this->randomizer) {
            try {
                return self::generateBytes($this->randomizer, 8);
            } catch (RandomException) {}
        }

        assert(($n = mt_getrandmax()) >= (1 << 31) - 1 && !($n & $n + 1));
        do {
            $c = mt_rand();
            $r = mt_rand() ^ mt_rand() << 31 ^ $c << 62;
        } while (!$r);

        return pack('Q', $r);
    }

    public function generateTraceIdBinary(): string {
        if ($this->randomizer) {
            try {
                return $this->generateBytes($this->randomizer, 16);
            } catch (RandomException) {}
        }

        assert(($n = mt_getrandmax()) >= (1 << 31) - 1 && !($n & $n + 1));
        do {
            $c = mt_rand();
            $hi = mt_rand() ^ mt_rand() << 31 ^ $c << 62;
            $lo = mt_rand() ^ mt_rand() << 31 ^ $c << 60;
        } while (!$hi && !$lo);

        return pack('Q2', $hi, $lo);
    }

    public function traceFlags(): int {
        return 0x2;
    }

    /**
     * @param positive-int $length
     */
    private static function generateBytes(Randomizer $randomizer, int $length): string {
        do {
            $bytes = $randomizer->getBytes($length);
        } while (strspn($bytes, "\0") === $length);

        return $bytes;
    }
}
