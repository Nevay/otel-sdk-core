<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Provider;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Trace\IdGenerator;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TracerProvider implements TracerProviderInterface, Provider {

    private readonly TracerState $tracerState;
    private readonly AttributesFactory $instrumentationScopeAttributesFactory;

    public function __construct(
        ?ContextStorageInterface $contextStorage,
        Resource $resource,
        AttributesFactory $instrumentationScopeAttributesFactory,
        Clock $clock,
        HighResolutionTime $highResolutionTime,
        IdGenerator $idGenerator,
        Sampler $sampler,
        SpanProcessor $spanProcessor,
        AttributesFactory $spanAttributesFactory,
        AttributesFactory $eventAttributesFactory,
        AttributesFactory $linkAttributesFactory,
        ?int $eventCountLimit,
        ?int $linkCountLimit,
        ?LoggerInterface $logger,
    ) {
        $this->tracerState = new TracerState(
            $contextStorage,
            $resource,
            $clock,
            $highResolutionTime,
            $idGenerator,
            $sampler,
            $spanProcessor,
            $spanAttributesFactory,
            $eventAttributesFactory,
            $linkAttributesFactory,
            $eventCountLimit,
            $linkCountLimit,
            $logger,
        );
        $this->instrumentationScopeAttributesFactory = $instrumentationScopeAttributesFactory;
    }

    public function getTracer(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): TracerInterface {
        if ($name === '') {
            $this->tracerState->logger?->warning('Invalid tracer name', ['name' => $name]);
        }

        return new Tracer($this->tracerState, new InstrumentationScope($name, $version, $schemaUrl,
            $this->instrumentationScopeAttributesFactory->builder()->addAll($attributes)->build()));
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->tracerState->spanProcessor->forceFlush($cancellation);
    }
}
