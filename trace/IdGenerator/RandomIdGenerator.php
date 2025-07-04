<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\IdGenerator;

use Nevay\OTelSDK\Trace\IdGenerator;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;

final class RandomIdGenerator implements IdGenerator {

    private readonly Randomizer $randomizer;

    /**
     * @param Randomizer $randomizer randomizer to use
     */
    public function __construct(Randomizer $randomizer = new Randomizer(new PcgOneseq128XslRr64())) {
        $this->randomizer = $randomizer;
    }

    public function generateSpanIdBinary(): string {
        do {
            $bytes = $this->randomizer->getBytes(8);
        } while ($bytes === "\0\0\0\0\0\0\0\0");

        return $bytes;
    }

    public function generateTraceIdBinary(): string {
        do {
            $bytes = $this->randomizer->getBytes(16);
        } while ($bytes === "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");

        return $bytes;
    }

    public function traceFlags(): int {
        return 0x2;
    }
}
