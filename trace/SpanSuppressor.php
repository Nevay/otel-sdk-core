<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Trace\Span\Kind;

/**
 * @experimental
 */
interface SpanSuppressor {

    public function resolveSuppression(Kind $spanKind, Attributes $attributes): SpanSuppression;
}
