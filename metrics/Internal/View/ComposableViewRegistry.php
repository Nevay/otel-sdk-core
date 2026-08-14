<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Closure;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Aggregation;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\View;

/**
 * @internal
 */
final class ComposableViewRegistry implements ViewRegistry {

    public function __construct(
        private readonly ViewRegistry $views,
    ) {}

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): iterable {
        $baseline = new View();
        $views = [];
        foreach ($this->views->find($instrument, $instrumentationScope) as $view) {
            if ($view->name === null) {
                $baseline = self::merge($view, $baseline);
                foreach ($views as $name => $composed) {
                    $views[$name] = self::merge($view, $composed);
                }
            } else {
                $views[$view->name] ??= $baseline;
                $views[$view->name] = self::merge($view, $views[$view->name]);
            }
        }

        return $views ?: [$baseline];
    }

    private static function merge(View $view, View $baseLine): View {
        return new View(
            name: $view->name ?? $baseLine->name,
            description: $view->description ?? $baseLine->description,
            attributeKeys: self::mergeAttributeKeysFilter($view->attributeKeys, $baseLine->attributeKeys),
            aggregation: self::mergeAggregation($view->aggregation, $baseLine->aggregation),
            exemplarReservoir: $view->exemplarReservoir ?? $baseLine->exemplarReservoir,
            cardinalityLimit: $view->cardinalityLimit ?? $baseLine->cardinalityLimit,
            enabled: $view->enabled ?? $baseLine->enabled,
        );
    }

    private static function mergeAttributeKeysFilter(?Closure $left, ?Closure $right): ?Closure {
        if ($left && $right) {
            return static fn(string $key): bool => $left($key) && $right($key);
        }

        return $left ?? $right;
    }

    private static function mergeAggregation(?Aggregation $left, ?Aggregation $right): ?Aggregation {
        if ($left && $right) {
            return new ComposedAggregation($left, $right);
        }

        return $left ?? $right;
    }
}
