<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Logs\Internal\LogDiscardedLogRecordProcessor;
use Nevay\OTelSDK\Logs\Internal\LoggerProvider;
use Nevay\OTelSDK\Logs\Internal\SelfDiagnosticsLogRecordProcessor;
use Nevay\OTelSDK\Logs\LogRecordProcessor\MultiLogRecordProcessor;
use Nevay\OTelSDK\Logs\LogRecordProcessor\NoopLogRecordProcessor;
use OpenTelemetry\API\Configuration\Context;
use Psr\Log\LoggerInterface;

final class LoggerProviderBuilder {

    private ?Resource $resource = null;
    /** @var list<LogRecordProcessor> */
    private array $logRecordProcessors = [];
    /** @var Configurator<LoggerConfig>|null */
    private ?Configurator $configurator = null;

    private ?int $attributeCountLimit = null;
    private ?int $attributeValueLengthLimit = null;
    private ?int $logRecordAttributeCountLimit = null;
    private ?int $logRecordAttributeValueLengthLimit = null;

    public function setResource(Resource $resource): self {
        $this->resource = $resource;

        return $this;
    }

    public function addLogRecordProcessor(LogRecordProcessor $logRecordProcessor): self {
        $this->logRecordProcessors[] = $logRecordProcessor;

        return $this;
    }

    /**
     * @param Configurator<LoggerConfig> $configurator
     *
     * @experimental
     */
    public function setLoggerConfigurator(Configurator $configurator): self {
        $this->configurator = $configurator;

        return $this;
    }

    public function setAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->attributeCountLimit = $attributeCountLimit;
        $this->attributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setLogRecordAttributeLimits(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): self {
        $this->logRecordAttributeCountLimit = $attributeCountLimit;
        $this->logRecordAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    /**
     * @internal
     */
    public function copyStateInto(LoggerProvider $loggerProvider, Context $selfDiagnostics): void {
        $loggerProvider->configurator = $this->configurator ?? new Configurator\NoopConfigurator();

        $loggerProvider->loggerState->logRecordAttributesFactory = AttributesLimitingFactory::create(
            $this->logRecordAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->logRecordAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );

        $logRecordProcessors = $this->logRecordProcessors;
        if ($loggerProvider->loggerState->logger) {
            $logRecordProcessors[] = new LogDiscardedLogRecordProcessor($loggerProvider->loggerState->logger);
        }
        $logRecordProcessors[] = new SelfDiagnosticsLogRecordProcessor($selfDiagnostics->meterProvider);

        $loggerProvider->loggerState->resource = $this->resource ?? Resource::default();
        $loggerProvider->loggerState->logRecordProcessor = MultiLogRecordProcessor::composite(...$logRecordProcessors);

        $loggerProvider->reload();
    }

    /**
     * @internal
     */
    public static function buildBase(?LoggerInterface $logger = null, (Clock&HighResolutionTime)|null $clock = null): LoggerProvider {
        return new LoggerProvider(
            null,
            Resource::default(),
            UnlimitedAttributesFactory::create(),
            new Configurator\NoopConfigurator(),
            $clock ?? SystemClock::create(),
            new NoopLogRecordProcessor(),
            AttributesLimitingFactory::create(),
            $logger,
        );
    }

    public function build(?LoggerInterface $logger = null): LoggerProviderInterface {
        $loggerProvider = self::buildBase($logger);
        $this->copyStateInto($loggerProvider, new Context());

        return $loggerProvider;
    }
}
