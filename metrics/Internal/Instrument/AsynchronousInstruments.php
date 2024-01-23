<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Instrument;

use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\Internal\Registry\MetricWriter;
use Nevay\OTelSDK\Metrics\Internal\StalenessHandler\ReferenceCounter;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use WeakMap;
use function Nevay\OTelSDK\Metrics\Internal\closure;
use function Nevay\OTelSDK\Metrics\Internal\weaken;

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
