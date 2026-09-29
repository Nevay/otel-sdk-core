<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Listener;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\Internal\Export\ExportListener;
use function assert;

/**
 * @internal
 */
final class QueueSizeListener implements ExportListener, QueueListener {

    private readonly int $maxQueueSize;
    private int $queueSize = 0;

    public function __construct(int $maxQueueSize) {
        $this->maxQueueSize = $maxQueueSize;
    }

    public static function create(int $maxQueueSize, bool|int $blocking): QueueListener {
        return match ($blocking) {
            false => new QueueSizeListener($maxQueueSize),
            true => new BlockingQueueSizeListener($maxQueueSize),
            default => new BlockingTimeoutQueueSizeListener($maxQueueSize, $blocking / 1000),
        };
    }

    public function maxQueueSize(): int {
        return $this->maxQueueSize;
    }

    public function queueSize(): int {
        return $this->queueSize;
    }

    public function acquireQueueSlot(): bool {
        if ($this->queueSize === $this->maxQueueSize) {
            return false;
        }

        $this->queueSize++;

        return true;
    }

    public function onExport(?int $count): void {
        // no-op
    }

    public function onFinished(?int $count): void {
        assert($count !== null);
        $this->queueSize -= $count;
    }

    public function drain(?Cancellation $cancellation = null): void {
        // no-op
    }
}
