<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\Cancellation;
use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;
use OpenTelemetry\API\Metrics\HistogramInterface;
use Revolt\EventLoop;
use Throwable;
use function count;
use function hrtime;

/**
 * @internal
 */
final class MultiMetricProducer implements MetricProducer {

    private readonly HistogramInterface $duration;
    private readonly array $attributes;

    /** @var array<MetricProducer> */
    public array $metricProducers = [];

    /**
     * @param iterable<MetricProducer> $metricProducers
     */
    public function __construct(iterable $metricProducers, HistogramInterface $duration, string $type, string $name) {
        foreach ($metricProducers as $metricProducer) {
            $this->metricProducers[] = $metricProducer;
        }

        $this->duration = $duration;
        $this->attributes = [
            'otel.component.name' => $name,
            'otel.component.type' => $type,
        ];
    }

    public function produce(Resource $resource, ?MetricFilter $metricFilter = null, ?Cancellation $cancellation = null): iterable {
        if (!$this->metricProducers) {
            return [];
        }

        $queue = new Queue(32);
        $pending = count($this->metricProducers);
        $start = hrtime(true);
        $handler = function(MetricProducer $metricProducer, Resource $resource, ?MetricFilter $metricFilter, ?Cancellation $cancellation, Queue $queue) use (&$pending, $start): void {
            if ($queue->isDisposed()) {
                return;
            }

            try {
                $metrics = $metricProducer->produce($resource, $metricFilter, $cancellation);
                unset($metricProducer, $metricFilter, $cancellation);

                foreach ($metrics as $metric) {
                    $queue->push($metric);
                }
            } catch (DisposedException) {
            } catch (Throwable $e) {
                if (!$queue->isComplete()) {
                    $queue->error($e);
                    $this->duration->record((hrtime(true) - $start) / 1e9, ['error.type' => $e::class, ...$this->attributes]);
                }
            } finally {
                if (!--$pending && !$queue->isComplete()) {
                    $queue->complete();
                    $this->duration->record((hrtime(true) - $start) / 1e9, $this->attributes);
                }
            }
        };
        foreach ($this->metricProducers as $metricProducer) {
            EventLoop::queue($handler, $metricProducer, $resource, $metricFilter, $cancellation, $queue);
        }

        return $queue->iterate();
    }
}
