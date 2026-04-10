<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class ComposableViewRegistry implements ViewRegistry {

    public function __construct(
        private readonly ViewRegistry $createViews,
        private readonly ViewRegistry $mergeViews,
    ) {}

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable {
        $baseLine = new View();
        foreach ($this->mergeViews->find($instrument, $instrumentationScope) as $view) {
            $baseLine = self::merge($view, $baseLine);
        }

        foreach ($this->createViews->find($instrument, $instrumentationScope) as $view) {
            yield self::merge($view, $baseLine);
        }
    }

    private static function merge(View $view, View $baseLine): View {
        return new View(
            name: $view->name ?? $baseLine->name,
            description: $view->description ?? $baseLine->description,
            attributeKeys: $view->attributeKeys ?? $baseLine->attributeKeys,
            aggregation: $view->aggregation ?? $baseLine->aggregation,
            exemplarReservoir: $view->exemplarReservoir ?? $baseLine->exemplarReservoir,
            cardinalityLimit: $view->cardinalityLimit ?? $baseLine->cardinalityLimit,
        );
    }
}
