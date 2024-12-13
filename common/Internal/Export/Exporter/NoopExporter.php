<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Exporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Common\Internal\Export\Exporter;

/**
 * @template TData
 * @implements Exporter<TData>
 *
 * @internal
 */
 abstract class NoopExporter implements Exporter {

     private bool $closed = false;

     public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
         if ($this->closed) {
             return Future::complete(false);
         }

         return Future::complete(true);
     }

     public function shutdown(?Cancellation $cancellation = null): bool {
         if ($this->closed) {
             return false;
         }

         $this->closed = true;

         return true;
     }

     public function forceFlush(?Cancellation $cancellation = null): bool {
         if ($this->closed) {
             return false;
         }

         return true;
     }
 }
