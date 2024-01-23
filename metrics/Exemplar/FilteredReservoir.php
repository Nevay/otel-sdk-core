<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use OpenTelemetry\Context\ContextInterface;

final class FilteredReservoir implements ExemplarReservoir {

    public function __construct(
        private readonly ExemplarReservoir $reservoir,
        private readonly ExemplarFilter $filter,
    ) {}

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        if ($this->filter->accepts($value, $attributes, $context, $timestamp)) {
            $this->reservoir->offer($value, $attributes, $context, $timestamp);
        }
    }

    public function collect(Attributes $dataPointAttributes): array {
        return $this->reservoir->collect($dataPointAttributes);
    }
}
