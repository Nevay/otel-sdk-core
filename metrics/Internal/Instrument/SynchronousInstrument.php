<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use Nevay\OTelSDK\Metrics\MeterConfig;
use OpenTelemetry\Context\ContextInterface;
use function assert;

/**
 * @internal
 */
trait SynchronousInstrument {

    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Instrument $instrument,
        private readonly ReferenceCounter $referenceCounter,
        private readonly MeterConfig $meterConfig,
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
        return !$this->meterConfig->disabled && $this->writer->enabled($this->instrument);
    }

    /**
     * @param float|int $amount
     * @param ContextInterface|false|null $context
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function write($amount, iterable $attributes = [], $context = null): void {
        if ($this->meterConfig->disabled) {
            return;
        }

        $this->writer->record($this->instrument, $amount, $attributes, $context);
    }
}
