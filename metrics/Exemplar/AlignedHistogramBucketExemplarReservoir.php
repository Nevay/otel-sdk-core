<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use OpenTelemetry\Context\ContextInterface;
use function count;

final class AlignedHistogramBucketExemplarReservoir implements ExemplarReservoir {

    private readonly BucketStorage $storage;
    private readonly array $boundaries;

    /**
     * @param list<float|int> $boundaries
     */
    public function __construct(array $boundaries) {
        $this->storage = new BucketStorage(count($boundaries) + 1);
        $this->boundaries = $boundaries;
    }

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        for ($i = 0, $n = count($this->boundaries); $i < $n && $this->boundaries[$i] < $value; $i++) {}
        $this->storage->store($i, $value, $attributes, $context, $timestamp);
    }

    public function collect(Attributes $dataPointAttributes): array {
        return $this->storage->collect($dataPointAttributes);
    }
}
