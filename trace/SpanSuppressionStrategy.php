<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

/**
 * @experimental
 */
interface SpanSuppressionStrategy {

    public function resolveSuppression(SamplingParams $params): SpanSuppression;
}
