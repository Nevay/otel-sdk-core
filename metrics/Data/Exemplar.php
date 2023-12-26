<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Data;

use Nevay\OtelSDK\Common\Attributes;
use OpenTelemetry\API\Trace\SpanContextInterface;

final class Exemplar {

    public function __construct(
        private readonly int|string $index,
        public readonly float|int $value,
        public readonly int $timestamp,
        public readonly Attributes $attributes,
        public readonly ?SpanContextInterface $spanContext,
    ) {}

    /**
     * @param iterable<Exemplar>|null $exemplars
     * @return array<list<Exemplar>>
     */
    public static function groupByIndex(iterable|null $exemplars): array {
        if (!$exemplars) {
            return [];
        }

        $grouped = [];
        foreach ($exemplars as $exemplar) {
            $grouped[$exemplar->index][] = $exemplar;
        }

        return $grouped;
    }
}
