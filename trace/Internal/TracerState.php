<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Internal;

use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\HighResolutionTime;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Trace\IdGenerator;
use Nevay\OtelSDK\Trace\Sampler;
use Nevay\OtelSDK\Trace\SpanProcessor;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TracerState {

    public function __construct(
        public readonly ?ContextStorageInterface $contextStorage,
        public readonly Resource $resource,
        public readonly Clock $clock,
        public readonly HighResolutionTime $highResolutionTime,
        public readonly IdGenerator $idGenerator,
        public readonly Sampler $sampler,
        public readonly SpanProcessor $spanProcessor,
        public readonly AttributesFactory $spanAttributesFactory,
        public readonly AttributesFactory $eventAttributesFactory,
        public readonly AttributesFactory $linkAttributesFactory,
        public readonly ?int $eventCountLimit,
        public readonly ?int $linkCountLimit,
        public readonly ?LoggerInterface $logger,
    ) {}
}
