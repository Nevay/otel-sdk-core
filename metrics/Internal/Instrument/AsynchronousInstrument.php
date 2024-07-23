<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use WeakMap;
use function assert;

/**
 * @internal
 */
trait AsynchronousInstrument {

    /**
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     */
    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Instrument $instrument,
        private readonly ReferenceCounter $referenceCounter,
        private readonly WeakMap $destructors,
    ) {
        assert($this instanceof InstrumentHandle);

        $this->referenceCounter->acquire();
    }

    public function __destruct() {
        $this->referenceCounter->release();
    }

    public function getHandle(): Instrument {
        return $this->instrument;
    }

    public function enabled(): bool {
        return $this->writer->enabled($this->instrument);
    }

    /**
     * @param callable(ObserverInterface): void $callback
     */
    public function observe(callable $callback): ObservableCallbackInterface {
        return AsynchronousInstruments::observe(
            $this->writer,
            $this->destructors,
            $callback,
            [$this->instrument],
            $this->referenceCounter,
        );
    }
}
