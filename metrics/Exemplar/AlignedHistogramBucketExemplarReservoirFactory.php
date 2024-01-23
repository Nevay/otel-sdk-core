<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\ExemplarReservoirFactory;

final class AlignedHistogramBucketExemplarReservoirFactory implements ExemplarReservoirFactory {

    public function __construct(
        private readonly array $boundaries,
    ) {}

    public function createExemplarReservoir(): ExemplarReservoir {
        return new AlignedHistogramBucketExemplarReservoir($this->boundaries);
    }
}
