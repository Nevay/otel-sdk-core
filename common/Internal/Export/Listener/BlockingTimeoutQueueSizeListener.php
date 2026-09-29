<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Listener;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\Internal\Export\ExportListener;
use Nevay\Sync\Internal\LocalSemaphore;
use Nevay\Sync\Internal\Semaphore;
use Revolt\EventLoop;
use Throwable;
use function assert;

/**
 * @internal
 */
final class BlockingTimeoutQueueSizeListener implements ExportListener, QueueListener {

    private readonly int $maxQueueSize;
    private readonly Semaphore $semaphore;
    private readonly float $blockingTimeout;

    public function __construct(int $maxQueueSize, float $blockingTimeout) {
        $this->maxQueueSize = $maxQueueSize;
        $this->semaphore = new LocalSemaphore();
        $this->blockingTimeout = $blockingTimeout;
    }

    public function maxQueueSize(): int {
        return $this->maxQueueSize;
    }

    public function queueSize(): int {
        return $this->semaphore->acquiredPermits();
    }

    public function acquireQueueSlot(): bool {
        if ($this->semaphore->acquire($this->maxQueueSize, blocking: false)) {
            return true;
        }

        $timer = EventLoop::delay($this->blockingTimeout, self::resumeCallback());

        try {
            return $this->semaphore->acquire($this->maxQueueSize);
        } finally {
            EventLoop::cancel($timer);
        }
    }

    public function onExport(?int $count): void {
        // no-op
    }

    public function onFinished(?int $count): void {
        assert($count !== null);
        $this->semaphore->release($count);
    }

    public function drain(?Cancellation $cancellation = null): void {
        $cancellationId = $cancellation?->subscribe(self::resumeCallback());

        try {
            $this->semaphore->acquire($this->maxQueueSize, permits: 0);
        } finally {
            $cancellation?->unsubscribe($cancellationId);
        }
    }

    private static function resumeCallback(): Closure {
        $suspension = EventLoop::getSuspension();
        return static function() use ($suspension): void {
            try {
                $suspension->resume();
            } catch (Throwable) {}
        };
    }
}
