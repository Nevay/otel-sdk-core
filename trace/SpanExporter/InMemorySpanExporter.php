<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\InMemoryExporter;
use Nevay\OTelSDK\Trace\ReadableSpan;
use Nevay\OTelSDK\Trace\SpanExporter;

/**
 * @implements InMemoryExporter<ReadableSpan>
 */
final class InMemorySpanExporter extends InMemoryExporter implements SpanExporter {

}
