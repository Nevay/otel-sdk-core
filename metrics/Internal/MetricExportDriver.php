<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Amp\TimeoutCancellation;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessorDriver;
use Nevay\OTelSDK\Metrics\Data\Metric;
use Nevay\OTelSDK\Metrics\MetricFilter;
use Nevay\OTelSDK\Metrics\MetricProducer;

/**
 * @implements ExportingProcessorDriver<iterable<Metric>, iterable<Metric>>
 *
 * @internal
 */
final class MetricExportDriver implements ExportingProcessorDriver {

    public function __construct(
        private readonly MetricProducer $metricProducer,
        private readonly ?MetricFilter $metricFilter,
        private readonly int $collectTimeoutMillis,
    ) {}

    public function getPending(): iterable {
        return $this->metricProducer->produce($this->metricFilter, new TimeoutCancellation($this->collectTimeoutMillis / 1000));
    }

    public function hasPending(): bool {
        return true;
    }

    public function isBuffered(): bool {
        return false;
    }

    public function count(mixed $data): null {
        return null;
    }

    public function finalize(mixed $data): iterable {
        return $data;
    }
}
