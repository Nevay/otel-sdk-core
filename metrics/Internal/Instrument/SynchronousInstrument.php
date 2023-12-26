<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\Context\ContextInterface;

trait SynchronousInstrument {

    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Instrument $instrument,
        private readonly ReferenceCounter $referenceCounter,
    ) {
        $this->referenceCounter->acquire();
    }

    public function __destruct() {
        $this->referenceCounter->release();
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
