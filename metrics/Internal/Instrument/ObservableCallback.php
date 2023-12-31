<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;

final class ObservableCallback implements ObservableCallbackInterface {

    public function __construct(
        private readonly MetricWriter $writer,
        private readonly ReferenceCounter $referenceCounter,
        private ?int $callbackId,
        private readonly ?ObservableCallbackDestructor $callbackDestructor,
        private ?object $target,
    ) {}

    public function detach(): void {
        if ($this->callbackId === null) {
            return;
        }

        $this->writer->unregisterCallback($this->callbackId);
        $this->referenceCounter->release();
        if ($this->callbackDestructor !== null) {
            unset($this->callbackDestructor->callbackIds[$this->callbackId]);
            if (!$this->callbackDestructor->callbackIds) {
                unset($this->callbackDestructor->destructors[$this->target]);
            }
        }

        $this->callbackId = null;
        $this->target = null;
    }

    public function __destruct() {
        if ($this->callbackDestructor !== null) {
            return;
        }
        if ($this->callbackId === null) {
            return;
        }

        $this->referenceCounter->acquire(true);
        $this->referenceCounter->release();
    }
}
