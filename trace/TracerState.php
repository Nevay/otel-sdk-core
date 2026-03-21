<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace;

use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;

final class TracerState {

    /**
     * @param Configurator<TracerConfig> $configurator
     * @param array<SpanProcessor> $spanProcessors
     */
    public function __construct(
        public Configurator $configurator,
        public IdGenerator $idGenerator,
        public Sampler $sampler,
        public array $spanProcessors,
        public Resource $resource,
        public AttributesFactory $spanAttributesFactory,
        public AttributesFactory $eventAttributesFactory,
        public AttributesFactory $linkAttributesFactory,
        public int $eventCountLimit,
        public int $linkCountLimit,
        public SpanSuppressionStrategy $spanSuppressionStrategy
    ) {}
}
