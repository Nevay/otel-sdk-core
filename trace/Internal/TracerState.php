<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\HighResolutionTime;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Trace\IdGenerator;
use Nevay\OTelSDK\Trace\Sampler;
use Nevay\OTelSDK\Trace\SpanProcessor;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TracerState {

    public function __construct(
        public readonly ?ContextStorageInterface $contextStorage,
        public Resource $resource,
        public readonly Clock $clock,
        public readonly HighResolutionTime $highResolutionTime,
        public IdGenerator $idGenerator,
        public Sampler $sampler,
        public SpanProcessor $spanProcessor,
        public SpanListener $spanListener,
        public AttributesFactory $spanAttributesFactory,
        public AttributesFactory $eventAttributesFactory,
        public AttributesFactory $linkAttributesFactory,
        public ?int $eventCountLimit,
        public ?int $linkCountLimit,
        public readonly ?LoggerInterface $logger,
    ) {}
}
