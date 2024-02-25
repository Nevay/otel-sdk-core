<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Revolt\EventLoop;
use function count;

final class MultiMetricProducer implements MetricProducer {

    /** @var list<MetricProducer> */
    public array $metricProducers = [];

    public function produce(?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable {
        $queue = new Queue();
        $count = 0;
        $pending = count($this->metricProducers);
        $handler = static function(iterable $metrics, Queue $queue) use (&$pending): void {
            try {
                if (!$queue->isDisposed()) {
                    foreach ($metrics as $metric) {
                        $queue->push($metric);
                    }
                }
            } catch (DisposedException) {
            } finally {
                if (!--$pending) {
                    $queue->complete();
                }
            }
        };
        foreach ($this->metricProducers as $metricProducer) {
            $metrics = $metricProducer->produce($metricFilter, $cancellation);
            $count += count($metrics);
            EventLoop::queue($handler, $metrics, $queue);
        }
        if (!$pending) {
            $queue->complete();
        }

        return new SizedTraversable($queue->iterate(), $count);
    }
}
