<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\InstrumentationScopeCache;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Logs\LoggerConfig;
use Nevay\OTelSDK\Logs\LoggerProviderInterface;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use WeakMap;

/**
 * @internal
 */
final class LoggerProvider implements LoggerProviderInterface {

    public readonly LoggerState $loggerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;
    private readonly InstrumentationScopeCache $instrumentationScopeCache;
    /** @var Configurator<LoggerConfig> */
    public Configurator $configurator;

    /** @var WeakMap<InstrumentationScope, Logger> */
    private WeakMap $loggers;

    /**
     * @param Configurator<LoggerConfig> $configurator
     */
    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Configurator $configurator,
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
        $this->instrumentationScopeCache = new InstrumentationScopeCache();
        $this->configurator = $configurator;
        $this->loggers = new WeakMap();
    }

    public function reload(): void {
        foreach ($this->loggers as $logger) {
            $config = new LoggerConfig();
            $this->configurator->update($config, $logger->instrumentationScope);

            if ($logger->disabled === $config->disabled && $logger->minimumSeverity === $config->minimumSeverity && $logger->traceBased === $config->traceBased) {
                continue;
            }

            $this->loggerState->logger?->debug('Updating logger', ['scope' => $logger->instrumentationScope, 'config' => $config]);

            $logger->disabled = $config->disabled;
            $logger->minimumSeverity = $config->minimumSeverity;
            $logger->traceBased = $config->traceBased;
        }
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

        if ($logger = $this->loggers[$instrumentationScope] ?? null) {
            return $logger;
        }

        $config = new LoggerConfig();
        $this->configurator->update($config, $instrumentationScope);

        $this->loggerState->logger?->debug('Creating logger', ['scope' => $instrumentationScope, 'config' => $config]);

        return $this->loggers[$instrumentationScope] = new Logger(
            $this->loggerState,
            $instrumentationScope,
            $config->disabled,
            $config->minimumSeverity,
            $config->traceBased,
        );
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->loggerState->logRecordProcessor->forceFlush($cancellation);
    }
}
