<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;

final class ObservableCallbackDestructor {

    /**
     * @param array<int, int> $callbackIds
     */
    public function __construct(
        private readonly MetricWriter $writer,
        private readonly ReferenceCounter $referenceCounter,
        public array $callbackIds = [],
    ) {}

    public function __destruct() {
        foreach ($this->callbackIds as $callbackId) {
            $this->writer->unregisterCallback($callbackId);
            $this->referenceCounter->release();
        }
    }
}
