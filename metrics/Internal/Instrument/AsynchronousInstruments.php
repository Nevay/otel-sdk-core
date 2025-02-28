<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Closure;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use ReflectionFunction;
use stdClass;
use WeakMap;
use WeakReference;
use function str_starts_with;

/**
 * @internal
 */
final class AsynchronousInstruments {

    /**
     * @param WeakMap<object, ObservableCallbackDestructor> $destructors
     * @param callable(ObserverInterface, ObserverInterface...): void $callback
     * @param non-empty-list<Instrument> $instruments
     */
    public static function observe(
        MetricWriter $writer,
        WeakMap $destructors,
        callable $callback,
        array $instruments,
        ReferenceCounter $referenceCounter,
    ): ObservableCallbackInterface {
        $target = null;
        $callback = self::weaken(self::closure($callback), $target);

        $callbackId = $writer->registerCallback($callback, ...$instruments);
        $referenceCounter->acquire();

        $destructor = null;
        if ($target) {
            $destructor = $destructors[$target] ??= new ObservableCallbackDestructor($destructors, $writer);
            $destructor->callbackIds[$callbackId] = $referenceCounter;
        }

        return new ObservableCallback($writer, $referenceCounter, $callbackId, $destructor, $target);
    }

    public static function closure(callable $callable): Closure {
        return $callable(...);
    }

    /**
     * @see https://github.com/amphp/amp/blob/f682341c856b1f688026f787bef4f77eaa5c7970/src/functions.php#L140-L191
     */
    private static function weaken(Closure $closure, ?object &$target = null): Closure {
        $reflection = new ReflectionFunction($closure);
        if (!$target = $reflection->getClosureThis()) {
            return $closure;
        }

        $scope = $reflection->getClosureScopeClass();
        $name = $reflection->getShortName();
        if (!str_starts_with($name, '{closure')) {
            $closure = fn(...$args) => $this->$name(...$args);
            if ($scope) {
                $closure = $closure->bindTo(null, $scope->name);
            }
        }

        static $placeholder = new stdClass();
        $ref = WeakReference::create($target);
        $closure = $closure->bindTo($placeholder);

        return $scope && $target::class === $scope->name && !$scope->isInternal()
            ? static fn(mixed ...$args): mixed => ($obj = $ref->get()) ? $closure->call($obj, ...$args) : null
            : static fn(mixed ...$args): mixed => ($obj = $ref->get()) ? $closure->bindTo($obj)(...$args) : null;
    }
}
