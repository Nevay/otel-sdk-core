<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\SpanSuppression;
use Nevay\OTelSDK\Trace\SpanSuppressionStrategy;
use Nevay\OTelSDK\Trace\SpanSuppressor;

/**
 * @internal
 */
final class SpanSuppressorProxy implements SpanSuppressor {

    private SpanSuppressionStrategy $proxy;
    private readonly InstrumentationScope $instrumentationScope;
    private ?SpanSuppressionStrategy $spanSuppressionStrategy = null;
    private ?SpanSuppressor $spanSuppressor = null;

    public function __construct(SpanSuppressionStrategy &$spanSuppressionStrategy, InstrumentationScope $instrumentationScope) {
        $this->proxy = &$spanSuppressionStrategy;
        $this->instrumentationScope = $instrumentationScope;
    }

    public function resolveSuppression(SamplingParams $params): SpanSuppression {
        if ($this->proxy !== $this->spanSuppressionStrategy) {
            $this->spanSuppressor = $this->proxy->getSuppressor($this->instrumentationScope);
            $this->spanSuppressionStrategy = $this->proxy;
        }

        return $this->spanSuppressor->resolveSuppression($params);
    }
}
