<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\NoopExporter;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\SpanExporter;

/**
 * @extends NoopExporter<ReadableSpan>
 */
final class NoopSpanExporter extends NoopExporter implements SpanExporter {

}
