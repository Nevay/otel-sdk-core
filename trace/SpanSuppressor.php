<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

/**
 * @experimental
 */
interface SpanSuppressor {

    public function resolveSuppression(SamplingParams $params): SpanSuppression;
}
