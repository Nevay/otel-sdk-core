<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use OpenTelemetry\Context\ContextInterface;

interface ExemplarReservoir {

    public function offer(int|string $index, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void;

    /**
     * @param array<Attributes> $dataPointAttributes
     * @return array<Exemplar>
     */
    public function collect(array $dataPointAttributes): array;
}
