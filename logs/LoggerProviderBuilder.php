<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs;

use Nevay\OtelSDK\Common\AttributesLimitingFactory;
use Nevay\OtelSDK\Common\Provider;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Common\SystemClock;
use Nevay\OtelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OtelSDK\Logs\Internal\LoggerProvider;
use Nevay\OtelSDK\Logs\LogRecordProcessor\MultiLogRecordProcessor;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use Psr\Log\LoggerInterface;

final class LoggerProviderBuilder {

    /** @var list<Resource> */
    private array $resources = [];
    /** @var list<LogRecordProcessor> */
    private array $logRecordProcessors = [];

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

    public function setAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->attributeCountLimit = $attributeCountLimit;
        $this->attributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function setLogRecordAttributeLimits(int $attributeCountLimit = null, int $attributeValueLengthLimit = null): self {
        $this->logRecordAttributeCountLimit = $attributeCountLimit;
        $this->logRecordAttributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    public function build(LoggerInterface $logger = null): LoggerProviderInterface&Provider {
        $logRecordAttributesFactory = AttributesLimitingFactory::create(
            $this->logRecordAttributeCountLimit ?? $this->attributeCountLimit ?? 128,
            $this->logRecordAttributeValueLengthLimit ?? $this->attributeValueLengthLimit,
        );

        return new LoggerProvider(
            null,
            Resource::mergeAll(...$this->resources),
            UnlimitedAttributesFactory::create(),
            SystemClock::create(),
            MultiLogRecordProcessor::composite(...$this->logRecordProcessors),
            $logRecordAttributesFactory,
            $logger,
        );
    }
}
