<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs\Internal;

use Amp\Cancellation;
use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Common\Provider;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Logs\LogRecordProcessor;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * @internal
 */
final class LoggerProvider implements LoggerProviderInterface, Provider {

    private readonly LoggerState $loggerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;

    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Clock $clock,
        LogRecordProcessor $logRecordProcessor,
        AttributesFactory $logRecordAttributesFactory,
        ?PsrLoggerInterface $logger,
    ) {
        $this->loggerState = new LoggerState(
            $contextStorage,
            $resource,
            $clock,
            $logRecordProcessor,
            $logRecordAttributesFactory,
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
    }

    public function getLogger(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): LoggerInterface {
        if ($name === '') {
            $this->loggerState->logger?->warning('Invalid logger name', ['name' => $name]);
        }

        return new Logger($this->loggerState, new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build()));
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->forceFlush($cancellation);
    }
}
