<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Logs\Internal\LogDiscardedLogRecordProcessor;
use Nevay\OTelSDK\Logs\Internal\LoggerProvider;
use Nevay\OTelSDK\Logs\LogRecordProcessor\MultiLogRecordProcessor;
use OpenTelemetry\API\Logs\EventLoggerProviderInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use Psr\Log\LoggerInterface;

final class LoggerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<LogRecordProcessor> */
    private array $logRecordProcessors = [];

    private ?Closure $loggerConfigurator = null;

    private ?int $attributeCountLimit = null;
    private ?int $attributeValueLengthLimit = null;
    private ?int $logRecordAttributeCountLimit = null;
    private ?int $logRecordAttributeValueLengthLimit = null;

    public function addResource(Resource $resource): self {
        $this->resources[] = $resource;

        return $this;
    }

    public function addLogRecordProcessor(LogRecordProcessor $logRecordProcessor): self {
        $this->logRecordProcessors[] = $logRecordProcessor;

        return $this;
    }

    /**
     * @param Closure(InstrumentationScope): LoggerConfig $loggerConfigurator
     *
     * @experimental
     */
    public function setLoggerConfigurator(Closure $loggerConfigurator): self {
        $this->loggerConfigurator = $loggerConfigurator;

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

    public function build(LoggerInterface $logger = null): LoggerProviderInterface&EventLoggerProviderInterface&Provider {
        $logRecordAttributesFactory = AttributesLimitingFactory::create(
            $this->logRecordAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->logRecordAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );

        $logRecordProcessors = $this->logRecordProcessors;
        if ($logger) {
            $logRecordProcessors[] = new LogDiscardedLogRecordProcessor($logger);
        }

        $loggerConfigurator = $this->loggerConfigurator
            ?? static fn(InstrumentationScope $instrumentationScope): LoggerConfig => new LoggerConfig();

        return new LoggerProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            $loggerConfigurator,
            SystemClock::create(),
            MultiLogRecordProcessor::composite(...$logRecordProcessors),
            $logRecordAttributesFactory,
            $logger,
        );
    }
}
