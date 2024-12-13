<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\InMemoryExporter;
use Nevay\OTelSDK\Logs\LogRecordExporter;
use Nevay\OTelSDK\Logs\ReadableLogRecord;

/**
 * @extends InMemoryExporter<ReadableLogRecord>
 */
final class InMemoryLogRecordExporter extends InMemoryExporter implements LogRecordExporter {

}
