<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\BucketStorage;
use OpenTelemetry\Context\ContextInterface;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;
use function array_fill;
use function count;

final class AlignedHistogramBucketExemplarReservoir implements ExemplarReservoir {

    private readonly Randomizer $randomizer;
    private readonly BucketStorage $storage;
    private readonly array $boundaries;

    private array $measurements;

    /**
     * @param list<float|int> $boundaries
     */
    public function __construct(array $boundaries, Randomizer $randomizer = new Randomizer(new PcgOneseq128XslRr64())) {
        $this->randomizer = $randomizer;
        $this->storage = new BucketStorage(count($boundaries) + 1);
        $this->boundaries = $boundaries;
        $this->measurements = array_fill(0, count($boundaries) + 1, 0);
    }

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        for ($i = 0, $n = count($this->boundaries); $i < $n && $this->boundaries[$i] < $value; $i++) {}

        $measurement = $this->randomizer->getInt(0, $this->measurements[$i]);
        $this->measurements[$i]++;

        if ($measurement === 0) {
            $this->storage->store($i, $value, $attributes, $context, $timestamp);
        }
    }

    public function collect(Attributes $dataPointAttributes): array {
        for ($i = 0; $i < count($this->measurements); $i++) {
            $this->measurements[$i] = 0;
        }

        return $this->storage->collect($dataPointAttributes);
    }
}
