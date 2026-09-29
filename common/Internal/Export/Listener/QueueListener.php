<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Listener;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\Internal\Export\ExportListener;

/**
 * @internal
 */
interface QueueListener extends ExportListener {

    public function maxQueueSize(): int;

    public function queueSize(): int;

    public function acquireQueueSlot(): bool;

    public function drain(?Cancellation $cancellation = null): void;
}
