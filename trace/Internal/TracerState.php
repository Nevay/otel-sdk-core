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

    public Resource $resource;
    public IdGenerator $idGenerator;
    public Sampler $sampler;
    public SpanProcessor $spanProcessor;
    public SpanListener $spanListener;

    public function __construct(
        public readonly ?ContextStorageInterface $contextStorage,
        public readonly Clock $clock,
        public readonly HighResolutionTime $highResolutionTime,
        public readonly AttributesFactory $spanAttributesFactory,
        public readonly AttributesFactory $eventAttributesFactory,
        public readonly AttributesFactory $linkAttributesFactory,
        public readonly ?int $eventCountLimit,
        public readonly ?int $linkCountLimit,
        public readonly ?LoggerInterface $logger,
    ) {}
}
