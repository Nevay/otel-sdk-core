<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Instrument;

use Nevay\OtelSDK\Metrics\Instrument;
use Nevay\OtelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OtelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use WeakMap;
use function Nevay\OtelSDK\Metrics\Internal\closure;
use function Nevay\OtelSDK\Metrics\Internal\weaken;

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
        $callback = weaken(closure($callback), $target);

        $callbackId = $writer->registerCallback($callback, ...$instruments);
        $referenceCounter->acquire();

        $destructor = null;
        if ($target) {
            $destructor = $destructors[$target] ??= new ObservableCallbackDestructor($destructors, $writer);
            $destructor->callbackIds[$callbackId] = $referenceCounter;
        }

        return new ObservableCallback($writer, $referenceCounter, $callbackId, $destructor, $target);
    }
}
