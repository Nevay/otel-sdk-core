<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Internal;

use Amp\Cancellation;
use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\HighResolutionTime;
use Nevay\OtelSDK\Common\InstrumentationScope;
use Nevay\OtelSDK\Common\Provider;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Trace\IdGenerator;
use Nevay\OtelSDK\Trace\Sampler;
use Nevay\OtelSDK\Trace\SpanProcessor;
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
