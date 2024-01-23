<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\Context\ContextInterface;
use function assert;

trait SynchronousInstrument {

    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Instrument $instrument,
        private readonly ReferenceCounter $referenceCounter,
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

    /**
     * @param float|int $amount
     * @param ContextInterface|false|null $context
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function write($amount, iterable $attributes = [], $context = null): void {
        $this->writer->record($this->instrument, $amount, $attributes, $context);
    }
}
