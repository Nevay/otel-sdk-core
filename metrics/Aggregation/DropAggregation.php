<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Aggregation;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\Data\Temporality;
use OpenTelemetry\Context\ContextInterface;

/**
 * @implements Aggregation<null, Data>
 */
final class DropAggregation implements Aggregation {

    public function initialize(): mixed {
        return null;
    }

    public function record(mixed $summary, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        // no-op
    }

    public function merge(mixed $left, mixed $right): mixed {
        return $right;
    }

    public function diff(mixed $left, mixed $right): mixed {
        return $right;
    }

    public function toData(array $attributes, array $summaries, array $exemplars, int $startTimestamp, int $timestamp, Temporality $temporality): Data {
        return new class implements Data {
            public array $dataPoints = [];
        };
    }
}