<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\LogRecordExporter;

use Nevay\OTelSDK\Common\Internal\Export\Exporter\NoopExporter;
use Nevay\OTelSDK\Logs\LogRecordExporter;
use Nevay\OTelSDK\Logs\ReadableLogRecord;

/**
 * @implements NoopExporter<ReadableLogRecord>
 */
final class NoopLogRecordExporter extends NoopExporter implements LogRecordExporter {

}
