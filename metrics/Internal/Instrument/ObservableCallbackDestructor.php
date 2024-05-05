<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use WeakMap;

/**
 * @internal
 */
final class ObservableCallbackDestructor {

    /**
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     * @param array<int, ReferenceCounter> $callbackIds
     */
    public function __construct(
        public readonly WeakMap $destructors,
        private readonly MetricWriter $writer,
        public array $callbackIds = [],
    ) {}

    public function __destruct() {
        foreach ($this->callbackIds as $callbackId => $referenceCounter) {
            $this->writer->unregisterCallback($callbackId);
            $referenceCounter->release();
        }
    }
}
