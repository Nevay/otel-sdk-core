<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Closure;
use ReflectionFunction;
use stdClass;
use WeakReference;
use function str_starts_with;

/**
 * @internal
 */
function closure(callable $callable): Closure {
    return $callable(...);
}

/**
 * @see https://github.com/amphp/amp/blob/f682341c856b1f688026f787bef4f77eaa5c7970/src/functions.php#L140-L191
 *
 * @internal
 */
function weaken(Closure $closure, ?object &$target = null): Closure {
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
