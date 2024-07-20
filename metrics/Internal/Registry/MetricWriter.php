<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Registry;

use Closure;
use Nevay\OTelSDK\Metrics\Instrument;
use OpenTelemetry\Context\ContextInterface;

/**
 * @internal
 */
interface MetricWriter {

    public function enabled(Instrument $instrument): bool;

    public function record(Instrument $instrument, float|int $value, iterable $attributes = [], ContextInterface|false|null $context = null): void;

    public function registerCallback(Closure $callback, Instrument $instrument, Instrument ...$instruments): int;

    public function unregisterCallback(int $callbackId): void;
}
