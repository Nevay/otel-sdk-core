<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\StalenessHandler;

use Closure;
use Revolt\EventLoop;

/**
 * @internal
 */
final class DelayedStalenessHandler implements StalenessHandler, ReferenceCounter {

    private readonly float $delay;
    private ?int $count = 0;
    private array $callbacks = [];
    private string $timerId = '';

    public function __construct(float $delay) {
        $this->delay = $delay;
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
        if ($this->count === null || --$this->count > 0) {
            return;
        }

        $callbacks = $this->callbacks;
        $this->timerId = EventLoop::unreference(EventLoop::delay($this->delay, static function() use (&$callbacks): void {
            $_callbacks = $callbacks;
            $callbacks = [];

            foreach ($_callbacks as $callback) {
                $callback();
            }
        }));
    }

    public function onStale(Closure $callback): void {
        if ($this->count !== null) {
            $this->callbacks[] = $callback;
        }
    }
}
