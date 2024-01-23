<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\SpanExporter;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Trace\SpanExporter;

final class NoopSpanExporter implements SpanExporter {

    public function export(iterable $batch, ?Cancellation $cancellation = null): Future {
        return Future::complete(true);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
