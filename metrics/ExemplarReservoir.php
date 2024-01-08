<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use OpenTelemetry\Context\ContextInterface;

interface ExemplarReservoir {

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void;

    /**
     * @return array<Exemplar>
     */
    public function collect(Attributes $dataPointAttributes): array;
}
