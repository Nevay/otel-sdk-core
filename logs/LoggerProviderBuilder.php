<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
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

    public function build(LoggerInterface|Context|null $selfDiagnostics = null, LoggerProvider $loggerProvider = new LoggerProvider()): LoggerProviderInterface {
        if ($selfDiagnostics instanceof LoggerInterface) {
            $selfDiagnostics = new Context(logger: $selfDiagnostics);
        }
        if ($selfDiagnostics) {
            $loggerProvider->initSelfDiagnostics($selfDiagnostics);
        }

        $loggerProvider->update(function(LoggerState $state): void {
            $state->configurator = $this->configurator ?? new Configurator\NoopConfigurator();
            $state->logRecordProcessors = $this->logRecordProcessors;
            $state->resource = $this->resource ?? Resource::default();
            $state->logRecordAttributesFactory = AttributesLimitingFactory::create(
                $this->logRecordAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
                $this->logRecordAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
            );
        });

        return $loggerProvider;
    }
}
