<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

use Amp\Cancellation;
use Amp\Future;

/**
 * @template TData
 *
 * @internal
 */
interface Exporter {

    /**
     * @param iterable<TData> $batch
     * @return Future<bool>
     */
    public function export(iterable $batch, ?Cancellation $cancellation = null): Future;

    public function shutdown(?Cancellation $cancellation = null): bool;

    public function forceFlush(?Cancellation $cancellation = null): bool;
}
