<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Stream;

use GMP;
use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Aggregation;
use Nevay\OtelSDK\Metrics\Data\Data;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use const INF;

/**
 * @template TSummary
 */
final class DeltaStorage {

    private readonly Aggregation $aggregation;
    private readonly ?int $cardinalityLimit;
    /** @var Delta<TSummary> */
    private readonly Delta $head;

    /**
     * @param Aggregation<TSummary, Data> $aggregation
     */
    public function __construct(Aggregation $aggregation, ?int $cardinalityLimit) {
        $this->aggregation = $aggregation;
        $this->cardinalityLimit = $cardinalityLimit;
        $this->head = new Delta(new Metric([], [], 0), 0);
        unset($this->head->metric);
    }

    /**
     * @param Metric<TSummary> $metric
     */
    public function add(Metric $metric, int|GMP $readers): void {
        if ($readers == 0) {
            return;
        }

        if (($this->head->prev->readers ?? null) != $readers) {
            $this->head->prev = new Delta($metric, $readers, $this->head->prev);
        } else {
            assert($this->head->prev !== null);
            $this->mergeInto($this->head->prev->metric, $metric);
        }
    }

    /**
     * @return Metric<TSummary>|null
     */
    public function collect(int $reader, bool $retain = false): ?Metric {
        $n = null;
        for ($d = $this->head; $d->prev; $d = $d->prev) {
            if (($d->prev->readers >> $reader & 1) != 0) {
                if ($n !== null) {
                    assert($n->prev !== null);
                    $n->prev->readers ^= $d->prev->readers;
                    $this->mergeInto($d->prev->metric, $n->prev->metric);
                    $this->tryUnlink($n);

                    if ($n->prev === $d->prev) {
                        continue;
                    }
                }

                $n = $d;
            }
        }

        $metric = $n->prev->metric ?? null;

        if (!$retain && $n) {
            assert($n->prev !== null);
            $n->prev->readers ^= ($n->prev->readers & 1 | 1) << $reader;
            $this->tryUnlink($n);
        }

        return $metric;
    }

    private function tryUnlink(Delta $n): void {
        assert($n->prev !== null);
        if ($n->prev->readers == 0) {
            $n->prev = $n->prev->prev;
            return;
        }

        for ($c = $n->prev->prev;
             $c && ($n->prev->readers & $c->readers) == 0;
             $c = $c->prev) {
        }

        if ($c && $n->prev->readers == $c->readers) {
            $this->mergeInto($c->metric, $n->prev->metric);
            $n->prev = $n->prev->prev;
        }
    }

    private function mergeInto(Metric $into, Metric $metric): void {
        $overflowExemplars = [];
        foreach ($metric->attributes as $k => $attributes) {
            $summary = $metric->summaries[$k];
            $exemplars = $metric->exemplars[$k] ?? [];

            if (Overflow::check($into->attributes, $k, $this->cardinalityLimit)) {
                self::mergeOverflowExemplars($overflowExemplars, $exemplars, $attributes->toArray());

                $k = Overflow::INDEX;
                $attributes = Overflow::attributes();
                $exemplars = [];
            }

            $into->attributes[$k] ??= $attributes;
            $into->summaries[$k] = isset($into->summaries[$k])
                ? $this->aggregation->merge($into->summaries[$k], $summary)
                : $summary;

            if ($exemplars) {
                $into->exemplars[$k] = $exemplars + ($into->exemplars[$k] ?? []);
            }
        }

        if ($overflowExemplars) {
            $k = Overflow::INDEX;
            self::mergeOverflowExemplars($overflowExemplars, $metric->exemplars[$k] ?? [], []);
            $into->exemplars[$k] = $overflowExemplars + ($into->exemplars[$k] ?? []);
        }
    }

    /**
     * @param array<Exemplar> $overflowExemplars
     * @param array<Exemplar> $exemplars
     */
    private static function mergeOverflowExemplars(array &$overflowExemplars, array $exemplars, array $dataPointAttributes): void {
        foreach ($exemplars as $i => $exemplar) {
            if ($exemplar->timestamp > ($overflowExemplars[$i]->timestamp ?? -INF)) {
                $overflowExemplars[$i] = new Exemplar(
                    $exemplar->value,
                    $exemplar->timestamp,
                    new Attributes($exemplar->attributes->toArray() + $dataPointAttributes, $exemplar->attributes->getDroppedAttributesCount()),
                    $exemplar->spanContext,
                );
            }
        }
    }
}
