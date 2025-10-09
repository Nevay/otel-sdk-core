<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Attributes;
use OpenTelemetry\Context\ContextInterface;

interface ExemplarReservoir {

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void;

    public function collect(Attributes $dataPointAttributes): Exemplars;
}
