<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar;

use Nevay\OtelSDK\Metrics\ExemplarReservoir;
use Nevay\OtelSDK\Metrics\ExemplarReservoirFactory;

final class AlignedHistogramBucketExemplarReservoirFactory implements ExemplarReservoirFactory {

    public function __construct(
        private readonly array $boundaries,
    ) {}

    public function createExemplarReservoir(): ExemplarReservoir {
        return new AlignedHistogramBucketExemplarReservoir($this->boundaries);
    }
}
