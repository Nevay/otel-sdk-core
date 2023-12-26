<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal;

use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\InstrumentType;
use Nevay\OtelSDK\Metrics\Internal\Instrument\AsynchronousInstrument;
use Nevay\OtelSDK\Metrics\Internal\Instrument\Counter;
use Nevay\OtelSDK\Metrics\Internal\Instrument\Gauge;
use Nevay\OtelSDK\Metrics\Internal\Instrument\Histogram;
use Nevay\OtelSDK\Metrics\Internal\Instrument\ObservableCounter;
use Nevay\OtelSDK\Metrics\Internal\Instrument\ObservableGauge;
use Nevay\OtelSDK\Metrics\Internal\Instrument\ObservableUpDownCounter;
use Nevay\OtelSDK\Metrics\Internal\Instrument\SynchronousInstrument;
use Nevay\OtelSDK\Metrics\Internal\Instrument\UpDownCounter;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableCounterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use function array_unshift;
use function assert;
use function class_uses;
use function in_array;
use function is_callable;

final class Meter implements MeterInterface {

    public function __construct(
        private readonly MeterState $meterState,
        private readonly InstrumentationScope $instrumentationScope,
    ) {}

    public function createCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): CounterInterface {
        return $this->createSynchronousInstrument(Counter::class,
            InstrumentType::Counter, $name, $unit, $description, $advisory);
    }

    public function createObservableCounter(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableCounterInterface {
        return $this->createAsynchronousInstrument(ObservableCounter::class,
            InstrumentType::AsynchronousCounter, $name, $unit, $description, $advisory, $callbacks);
    }

    public function createHistogram(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): HistogramInterface {
        return $this->createSynchronousInstrument(Histogram::class,
            InstrumentType::Histogram, $name, $unit, $description, $advisory);
    }

    /**
     * @experimental
     */
    public function createGauge(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): GaugeInterface {
        return $this->createSynchronousInstrument(Gauge::class,
            InstrumentType::Gauge, $name, $unit, $description, $advisory);
    }

    public function createObservableGauge(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableGaugeInterface {
        return $this->createAsynchronousInstrument(ObservableGauge::class,
            InstrumentType::AsynchronousGauge, $name, $unit, $description, $advisory, $callbacks);
    }

    public function createUpDownCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): UpDownCounterInterface {
        return $this->createSynchronousInstrument(UpDownCounter::class,
            InstrumentType::UpDownCounter, $name, $unit, $description, $advisory);
    }

    public function createObservableUpDownCounter(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableUpDownCounterInterface {
        return $this->createAsynchronousInstrument(ObservableUpDownCounter::class,
            InstrumentType::AsynchronousUpDownCounter, $name, $unit, $description, $advisory, $callbacks);
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    private function createSynchronousInstrument(string $class, InstrumentType $type, string $name, ?string $unit, ?string $description, array $advisory): object {
        assert(in_array(SynchronousInstrument::class, class_uses($class), true));

        [$instrument, $referenceCounter] = $this->meterState->createSynchronousInstrument(new Instrument(
            $type, $name, $unit, $description, $advisory), $this->instrumentationScope);

        return new $class($this->meterState->registry, $instrument, $referenceCounter);
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param array<callable> $callbacks
     * @return T
     */
    private function createAsynchronousInstrument(string $class, InstrumentType $type, string $name, ?string $unit, ?string $description, array|callable $advisory, array $callbacks): object {
        assert(in_array(AsynchronousInstrument::class, class_uses($class), true));

        if (is_callable($advisory)) {
            array_unshift($callbacks, $advisory);
            $advisory = [];
        }
        [$instrument, $referenceCounter, $destructors] = $this->meterState->createAsynchronousInstrument(new Instrument(
            $type, $name, $unit, $description, $advisory), $this->instrumentationScope);

        foreach ($callbacks as $callback) {
            $this->meterState->registry->registerCallback(closure($callback), $instrument);
            $referenceCounter->acquire(true);
        }

        return new $class($this->meterState->registry, $instrument, $referenceCounter, $destructors);
    }
}
