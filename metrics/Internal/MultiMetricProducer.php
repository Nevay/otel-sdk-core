<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;
use Revolt\EventLoop;
use Throwable;
use function count;

/**
 * @internal
 */
final class MultiMetricProducer implements MetricProducer {

    /** @var list<MetricProducer> */
    public array $metricProducers = [];

    /**
     * @param iterable<MetricProducer> $metricProducers
     */
    public function __construct(iterable $metricProducers = []) {
        foreach ($metricProducers as $metricProducer) {
            $this->metricProducers[] = $metricProducer;
        }
    }

    public function produce(?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable {
        if (!$this->metricProducers) {
            return [];
        }

        $queue = new Queue();
        $pending = count($this->metricProducers);
        $handler = static function(MetricProducer $metricProducer, ?MetricFilter $metricFilter, ?Cancellation $cancellation, Queue $queue) use (&$pending): void {
            if ($queue->isDisposed()) {
                return;
            }

            try {
                $metrics = $metricProducer->produce($metricFilter, $cancellation);
                unset($metricProducer, $metricFilter, $cancellation);

                foreach ($metrics as $metric) {
                    $queue->push($metric);
                }
            } catch (DisposedException) {
            } catch (Throwable $e) {
                $queue->error($e);
            } finally {
                if (!--$pending) {
                    $queue->complete();
                }
            }
        };
        foreach ($this->metricProducers as $metricProducer) {
            EventLoop::queue($handler, $metricProducer, $metricFilter, $cancellation, $queue);
        }

        return $queue->iterate();
    }
}
