<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Stream;

use GMP;
use Nevay\OTelSDK\Metrics\Aggregator;
use Nevay\OTelSDK\Metrics\Data\Data;
use Nevay\OTelSDK\Metrics\Data\DataPoint;

/**
 * @template TSummary
 *
 * @internal
 */
final class DeltaStorage {

    private readonly Aggregator $aggregator;
    private readonly ?int $cardinalityLimit;
    /** @var Delta<TSummary> */
    private readonly Delta $head;

    /**
     * @param Aggregator<TSummary, Data, DataPoint> $aggregator
     */
    public function __construct(Aggregator $aggregator, ?int $cardinalityLimit) {
        $this->aggregator = $aggregator;
        $this->cardinalityLimit = $cardinalityLimit;
        $this->head = new Delta(new Metric([], 0), 0);
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
        foreach ($metric->metricPoints as $index => $delta) {
            if (Overflow::check($into->metricPoints, $index, $this->cardinalityLimit)) {
                $index = Overflow::INDEX;
                $delta = new MetricPoint(
                    Overflow::attributes(),
                    $delta->summary,
                    $delta->exemplars->enrich($delta->attributes),
                );
            }

            $target = $into->metricPoints[$index] ??= new MetricPoint(
                $delta->attributes,
                $this->aggregator->initialize(),
            );

            $target->summary = $this->aggregator->merge($target->summary, $delta->summary);
            $target->exemplars = $delta->exemplars->merge($target->exemplars);
        }
    }
}
