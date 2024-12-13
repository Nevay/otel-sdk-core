<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Exporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Common\Internal\Export\Exporter;
use function Amp\async;

/**
 * @template TData
 * @implements Exporter<TData>
 *
 * @internal
 */
abstract class InMemoryExporter implements Exporter {

    /** @var list<TData> */
    private array $data = [];

    /** @var array<int, Future> */
    private array $pending = [];

    private bool $closed = false;

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        if ($this->closed) {
            return Future::complete(false);
        }

        $future = async(
            function(iterable $batch): bool {
                foreach ($batch as $datum) {
                    $this->data[] = $datum;
                }

                return true;
            },
            $batch,
        );

        $id = array_key_last($this->pending) + 1;
        $this->pending[$id] = $future->finally(function() use ($id): void {
            unset($this->pending[$id]);
        });

        return $future;
    }

    /**
     * @return list<TData>
     */
    public function collect(bool $reset = false): array {
        $data = $this->data;
        if ($reset) {
            $this->data = [];
        }

        return $data;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;
        foreach (Future::iterate($this->pending, $cancellation) as $ignored) {}

        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        if ($this->closed) {
            return false;
        }

        foreach (Future::iterate($this->pending, $cancellation) as $ignored) {}

        return true;
    }
}
