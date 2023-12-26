<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use WeakMap;
use function Nevay\OtelSDK\Metrics\Internal\closure;
use function Nevay\OtelSDK\Metrics\Internal\weaken;

trait AsynchronousInstrument {

    /**
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     */
    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Instrument $instrument,
        private readonly ReferenceCounter $referenceCounter,
        private readonly WeakMap $destructors
    ) {
        $this->referenceCounter->acquire();
    }

    public function __destruct() {
        $this->referenceCounter->release();
    }

    /**
     * @param callable(ObserverInterface): void $callback
     */
    public function observe(callable $callback, bool $weaken = false): ObservableCallbackInterface {
        $target = null;
        $callback = closure($callback);
        if ($weaken) {
            $callback = weaken($callback, $target);
        }

        $callbackId = $this->writer->registerCallback($callback, $this->instrument);
        $this->referenceCounter->acquire();

        $destructor = null;
        if ($object = $target) {
            /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
            $destructor = $this->destructors[$object] ??= new ObservableCallbackDestructor($this->writer, $this->referenceCounter);
            $destructor->callbackIds[$callbackId] = $callbackId;
        }

        return new ObservableCallback($this->writer, $this->referenceCounter, $callbackId, $destructor, $target);
    }
}
