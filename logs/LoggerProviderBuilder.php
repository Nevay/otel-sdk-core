<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Internal\ConfiguratorStack;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Logs\Internal\LogDiscardedLogRecordProcessor;
use Nevay\OTelSDK\Logs\Internal\LoggerProvider;
use Nevay\OTelSDK\Logs\Internal\SelfDiagnosticsLogRecordProcessor;
use Nevay\OTelSDK\Logs\LogRecordProcessor\MultiLogRecordProcessor;
use OpenTelemetry\API\Configuration\Context;
use Psr\Log\LoggerInterface;

final class LoggerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<LogRecordProcessor> */
    private array $logRecordProcessors = [];
    /** @var ConfiguratorStack<LoggerConfig> */
    private readonly ConfiguratorStack $loggerConfigurator;

    private ?Clock $clock = null;

    private ?int $attributeCountLimit = null;
    private ?int $attributeValueLengthLimit = null;
    private ?int $logRecordAttributeCountLimit = null;
    private ?int $logRecordAttributeValueLengthLimit = null;

    public function __construct() {
        $this->loggerConfigurator = new ConfiguratorStack(
            static fn() => new LoggerConfig(),
            static fn(LoggerConfig $loggerConfig) => $loggerConfig->__construct(),
        );
    }

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }

    public function addLogRecordProcessor(LogRecordProcessor $logRecordProcessor): self {
        $this->logRecordProcessors[] = $logRecordProcessor;

        return $this;
    }

    /**
     * @param Configurator<LoggerConfig>|Closure(LoggerConfig, InstrumentationScope): void $configurator
     *
     * @experimental
     */
    public function addLoggerConfigurator(Configurator|Closure $configurator): self {
        $this->loggerConfigurator->push($configurator);

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
     * @experimental
     */
    public function setClock(Clock&HighResolutionTime $clock): self {
        $this->clock = $clock;

        return $this;
    }

    /**
     * @internal
     */
    public function copyStateInto(LoggerProvider $loggerProvider, Context $selfDiagnostics): void {
        $logRecordProcessors = $this->logRecordProcessors;
        if ($loggerProvider->loggerState->logger) {
            $logRecordProcessors[] = new LogDiscardedLogRecordProcessor($loggerProvider->loggerState->logger);
        }
        $logRecordProcessors[] = new SelfDiagnosticsLogRecordProcessor($selfDiagnostics->meterProvider);

        $loggerProvider->loggerState->logRecordProcessor = MultiLogRecordProcessor::composite(...$logRecordProcessors);

        $loggerProvider->updateConfigurator(new Configurator\NoopConfigurator());
    }

    /**
     * @internal
     */
    public function buildBase(?LoggerInterface $logger = null): LoggerProvider {
        $logRecordAttributesFactory = AttributesLimitingFactory::create(
            $this->logRecordAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->logRecordAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );

        $loggerConfigurator = clone $this->loggerConfigurator;
        $loggerConfigurator->push(static fn(LoggerConfig $loggerConfig) => $loggerConfig->disabled = true);

        $clock = $this->clock ?? SystemClock::create();

        return new LoggerProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            $loggerConfigurator,
            $clock,
            $logRecordAttributesFactory,
            $logger,
        );
    }

    public function build(?LoggerInterface $logger = null): LoggerProviderInterface {
        $loggerProvider = $this->buildBase($logger);
        $this->copyStateInto($loggerProvider, new Context());

        return $loggerProvider;
    }
}
