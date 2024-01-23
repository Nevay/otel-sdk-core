<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

use Closure;
use Revolt\EventLoop;

final class DelayedStalenessHandler implements StalenessHandler, ReferenceCounter {

    private ?int $count = 0;
    private array $callbacks = [];
    private string $timerId;

    public function __construct(float $delay) {
        $callbacks = &$this->callbacks;
        $this->timerId = $timerId = EventLoop::disable(EventLoop::unreference(EventLoop::repeat($delay, static function() use (&$callbacks, &$timerId): void {
            $_callbacks = $callbacks;
            $callbacks = [];
            EventLoop::disable($timerId);

            foreach ($_callbacks as $callback) {
                $callback();
            }
        })));
    }

    public function __destruct() {
        $this->count = null;
        EventLoop::cancel($this->timerId);
    }

    public function acquire(bool $persistent = false): void {
        if ($persistent) {
            $this->count = null;
            $this->callbacks = [];
            EventLoop::cancel($this->timerId);
        }
        if ($this->count !== null) {
            $this->count++;
            EventLoop::disable($this->timerId);
        }
    }

    public function release(): void {
        if (--$this->count === 0) {
            EventLoop::enable($this->timerId);
        }
    }

    public function onStale(Closure $callback): void {
        if ($this->count !== null) {
            $this->callbacks[] = $callback;
        }
    }
}
