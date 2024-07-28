<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\Internal\Instrument\AsynchronousInstruments;
use Nevay\OTelSDK\Metrics\Internal\Instrument\Counter;
use Nevay\OTelSDK\Metrics\Internal\Instrument\Gauge;
use Nevay\OTelSDK\Metrics\Internal\Instrument\Histogram;
use Nevay\OTelSDK\Metrics\Internal\Instrument\InstrumentHandle;
use Nevay\OTelSDK\Metrics\Internal\Instrument\ObservableCounter;
use Nevay\OTelSDK\Metrics\Internal\Instrument\ObservableGauge;
use Nevay\OTelSDK\Metrics\Internal\Instrument\ObservableUpDownCounter;
use Nevay\OTelSDK\Metrics\Internal\Instrument\UpDownCounter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\MultiReferenceCounter;
use Nevay\OTelSDK\Metrics\MeterConfig;
use OpenTelemetry\API\Metrics\AsynchronousInstrument;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObservableCounterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use function array_unshift;
use function is_callable;

/**
 * @internal
 */
final class Meter implements MeterInterface {

    public function __construct(
        private readonly MeterState $meterState,
        private readonly InstrumentationScope $instrumentationScope,
        private readonly MeterConfig $meterConfig,
    ) {}

    private static function dummyInstrument(): Instrument {
        static $dummy;
        return $dummy ??= (new \ReflectionClass(Instrument::class))->newInstanceWithoutConstructor();
    }

    public function batchObserve(callable $callback, AsynchronousInstrument $instrument, AsynchronousInstrument ...$instruments): ObservableCallbackInterface {
        $referenceCounters = [];
        $handles = [];
        foreach ([$instrument, ...$instruments] as $instrument) {
            if (!$instrument instanceof InstrumentHandle) {
                $this->meterState->logger?->warning('Ignoring invalid instrument provided to batchObserve, instrument not created by this SDK', ['instrument' => $instrument]);
                $handles[] = self::dummyInstrument();
                continue;
            }

            $asynchronousInstrument = $this->meterState
                ->getAsynchronousInstrument($instrument->getHandle(), $this->instrumentationScope);

            if (!$asynchronousInstrument) {
                $this->meterState->logger?->warning('Ignoring invalid instrument provided to batchObserve, instrument not created by this meter', ['instrument' => $instrument]);
                $handles[] = self::dummyInstrument();
                continue;
            }

            [
                $handles[],
                $referenceCounters[],
            ] = $asynchronousInstrument;
        }

        return AsynchronousInstruments::observe(
            $this->meterState->registry,
            $this->meterState->destructors,
            $callback,
            $handles,
            new MultiReferenceCounter($referenceCounters),
        );
    }

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
     * @template T of InstrumentHandle
     * @param class-string<T> $class
     * @return T
     */
    private function createSynchronousInstrument(string $class, InstrumentType $type, string $name, ?string $unit, ?string $description, array $advisory): InstrumentHandle {
        [$instrument, $referenceCounter] = $this->meterState->createSynchronousInstrument(new Instrument(
            $type, $name, $unit, $description, $advisory), $this->instrumentationScope, $this->meterConfig);

        return new $class($this->meterState->registry, $instrument, $referenceCounter);
    }

    /**
     * @template T of InstrumentHandle
     * @param class-string<T> $class
     * @param array<callable> $callbacks
     * @return T
     */
    private function createAsynchronousInstrument(string $class, InstrumentType $type, string $name, ?string $unit, ?string $description, array|callable $advisory, array $callbacks): InstrumentHandle {
        if (is_callable($advisory)) {
            array_unshift($callbacks, $advisory);
            $advisory = [];
        }
        [$instrument, $referenceCounter] = $this->meterState->createAsynchronousInstrument(new Instrument(
            $type, $name, $unit, $description, $advisory), $this->instrumentationScope, $this->meterConfig);

        foreach ($callbacks as $callback) {
            $this->meterState->registry->registerCallback(closure($callback), $instrument);
            $referenceCounter->acquire(true);
        }

        return new $class($this->meterState->registry, $instrument, $referenceCounter, $this->meterState->destructors);
    }
}
