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
use function trigger_error;
use const E_USER_DEPRECATED;

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
            if (!$r = $this->meterState->getInstrument($instrument->getHandle(), $this->instrumentationScope)) {
                $this->meterState->logger?->warning('Ignoring invalid instrument provided to batchObserve, instrument not created by this meter', ['instrument' => $instrument]);
                $handles[] = self::dummyInstrument();
                continue;
            }

            $handles[] = $r->instrument;
            $referenceCounters[] = $r->referenceCounter;
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
        $r = $this->createInstrument(InstrumentType::Counter, $name, $unit, $description, $advisory);
        return new Counter($this->meterState->registry, $r->instrument, $r->referenceCounter);
    }

    public function createObservableCounter(string $name, ?string $unit = null, ?string $description = null, array|callable $advisory = [], callable ...$callbacks): ObservableCounterInterface {
        $r = $this->createInstrument(InstrumentType::AsynchronousCounter, $name, $unit, $description, self::bcAdvisoryCallback($advisory, $callbacks), $callbacks);
        return new ObservableCounter($this->meterState->registry, $r->instrument, $r->referenceCounter, $this->meterState->destructors);
    }

    public function createHistogram(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): HistogramInterface {
        $r = $this->createInstrument(InstrumentType::Histogram, $name, $unit, $description, $advisory);
        return new Histogram($this->meterState->registry, $r->instrument, $r->referenceCounter);
    }

    public function createGauge(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): GaugeInterface {
        $r = $this->createInstrument(InstrumentType::Gauge, $name, $unit, $description, $advisory);
        return new Gauge($this->meterState->registry, $r->instrument, $r->referenceCounter);
    }

    public function createObservableGauge(string $name, ?string $unit = null, ?string $description = null, array|callable $advisory = [], callable ...$callbacks): ObservableGaugeInterface {
        $r = $this->createInstrument(InstrumentType::AsynchronousGauge, $name, $unit, $description, self::bcAdvisoryCallback($advisory, $callbacks), $callbacks);
        return new ObservableGauge($this->meterState->registry, $r->instrument, $r->referenceCounter, $this->meterState->destructors);
    }

    public function createUpDownCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): UpDownCounterInterface {
        $r = $this->createInstrument(InstrumentType::UpDownCounter, $name, $unit, $description, $advisory);
        return new UpDownCounter($this->meterState->registry, $r->instrument, $r->referenceCounter);
    }

    public function createObservableUpDownCounter(string $name, ?string $unit = null, ?string $description = null, array|callable $advisory = [], callable ...$callbacks): ObservableUpDownCounterInterface {
        $r = $this->createInstrument(InstrumentType::AsynchronousUpDownCounter, $name, $unit, $description, self::bcAdvisoryCallback($advisory, $callbacks), $callbacks);
        return new ObservableUpDownCounter($this->meterState->registry, $r->instrument, $r->referenceCounter, $this->meterState->destructors);
    }

    private function createInstrument(InstrumentType $type, string $name, ?string $unit, ?string $description, array $advisory, array $callbacks = []): RegisteredInstrument {
        $r = $this->meterState->createInstrument(new Instrument($type, $name, $unit, $description, $advisory), $this->instrumentationScope, $this->meterConfig);

        foreach ($callbacks as $callback) {
            $this->meterState->registry->registerCallback(closure($callback), $r->instrument);
            $r->referenceCounter->acquire(true);
        }

        return $r;
    }

    private static function bcAdvisoryCallback(array|callable $advisory, array &$callbacks): array {
        if (!is_callable($advisory)) {
            return $advisory;
        }

        @trigger_error('Since open-telemetry/api 1.0.1: Passing an instrument callback instead of an advisory argument is deprecated, either add an empty advisory arguments or use a named argument for the callback', E_USER_DEPRECATED);
        array_unshift($callbacks, $advisory);

        return [];
    }
}
