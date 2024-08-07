<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Logs\LoggerConfig;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use OpenTelemetry\API\Logs\EventLoggerInterface;
use OpenTelemetry\API\Logs\EventLoggerProviderInterface;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use WeakMap;

/**
 * @internal
 */
final class LoggerProvider implements LoggerProviderInterface, EventLoggerProviderInterface, Provider {

    private readonly LoggerState $loggerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    private readonly WeakMap $configCache;
    private readonly Closure $loggerConfigurator;

    /**
     * @param Closure(InstrumentationScope): LoggerConfig $loggerConfigurator
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Closure $loggerConfigurator,
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
        $this->instrumentationScopeCache = new InstrumentationScopeCache($logger);
        $this->configCache = new WeakMap();
        $this->loggerConfigurator = $loggerConfigurator;
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

        $instrumentationScope = new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build());
        $instrumentationScope = $this->instrumentationScopeCache->intern($instrumentationScope);

        /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
        $loggerConfig = $this->configCache[$instrumentationScope] ??= ($this->loggerConfigurator)($instrumentationScope);

        return new Logger($this->loggerState, $instrumentationScope, $loggerConfig);
    }

    public function getEventLogger(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): EventLoggerInterface {
        if ($name === '') {
            $this->loggerState->logger?->warning('Invalid event logger name', ['name' => $name]);
        }

        $instrumentationScope = new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build());
        $instrumentationScope = $this->instrumentationScopeCache->intern($instrumentationScope);

        /** @noinspection PhpSecondWriteToReadonlyPropertyInspection */
        $loggerConfig = $this->configCache[$instrumentationScope] ??= ($this->loggerConfigurator)($instrumentationScope);

        return new EventLogger($this->loggerState, $instrumentationScope, $loggerConfig);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->forceFlush($cancellation);
    }
}
