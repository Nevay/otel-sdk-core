<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\SpanExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OtelSDK\Trace\ReadableSpan;
use Nevay\OtelSDK\Trace\SpanExporter;

final class InMemorySpanExporter implements SpanExporter {

    /** @var list<ReadableSpan> */
    private array $spans = [];

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        foreach ($batch as $span) {
            $this->spans[] = $span;
        }

        return Future::complete(true);
    }

    /**
     * @return list<ReadableSpan>
     */
    public function collect(bool $reset = false): array {
        $spans = $this->spans;
        if ($reset) {
            $this->spans = [];
        }

        return $spans;
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
