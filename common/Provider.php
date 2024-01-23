<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Amp\Cancellation;
use Amp\CancelledException;

interface Provider {

    /**
     * Shuts down the provider.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the provider was shut down successfully
     * @throws CancelledException if cancelled by the given cancellation
     */
    public function shutdown(?Cancellation $cancellation = null): bool;

    /**
     * Force flushes the provider.
     *
     * @param Cancellation|null $cancellation cancellation after which the call
     *        should be aborted
     * @return bool whether the provider was force flushed successfully
     * @throws CancelledException if cancelled by the given cancellation
     */
    public function forceFlush(?Cancellation $cancellation = null): bool;
}
