<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Registry;

use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\ContextResolver;
use Nevay\OtelSDK\Metrics\Internal\Stream\MetricAggregator;
use OpenTelemetry\API\Metrics\ObserverInterface;

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
        $attributes = $this->attributesFactory->builder()->addAll($attributes)->build();
        foreach ($this->writers as $writer) {
            $writer->record($amount, $attributes, $context, $this->timestamp);
        }
    }
}
