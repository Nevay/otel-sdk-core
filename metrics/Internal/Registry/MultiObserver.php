<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Registry;

use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\ContextResolver;
use Nevay\OTelSDK\Metrics\Internal\Stream\MetricAggregator;
use OpenTelemetry\API\Metrics\ObserverInterface;

/**
 * @internal
 */
final class MultiObserver implements ObserverInterface {

    private AttributesFactory $attributesFactory;
    private int $timestamp;

    /** @var list<MetricAggregator> */
    public array $writers = [];

    public function __construct(AttributesFactory $attributesFactory, int $timestamp) {
        $this->attributesFactory = $attributesFactory;
        $this->timestamp = $timestamp;
    }

    public function observe($amount, iterable $attributes = []): void {
        $context = ContextResolver::emptyContext();
        $attributes = $this->attributesFactory->build($attributes);
        foreach ($this->writers as $writer) {
            $writer->record($amount, $attributes, $context, $this->timestamp);
        }
    }
}
