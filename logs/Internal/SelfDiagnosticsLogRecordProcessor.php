<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Amp\Cancellation;
use Composer\InstalledVersions;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use Nevay\OTelSDK\Logs\ReadWriteLogRecord;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
final class SelfDiagnosticsLogRecordProcessor implements LogRecordProcessor {

    private readonly CounterInterface $createdCount;

    public function __construct(MeterProviderInterface $meterProvider) {
        $meter = $meterProvider->getMeter(
            'com.tobiasbachert.otel.sdk.logs',
            InstalledVersions::getVersionRanges('tbachert/otel-sdk-logs'),
            'https://opentelemetry.io/schemas/1.36.0',
        );

        $this->createdCount = $meter->createCounter(
            'otel.sdk.log.created',
            '{log_record}',
            'The number of logs submitted to enabled SDK Loggers',
        );
    }

    public function enabled(ContextInterface $context, InstrumentationScope $instrumentationScope, ?int $severityNumber, ?string $eventName): bool {
        return false;
    }

    public function onEmit(ReadWriteLogRecord $logRecord, ContextInterface $context): void {
        $this->createdCount->add(1);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
