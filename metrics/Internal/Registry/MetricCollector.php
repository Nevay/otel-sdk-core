<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Internal\Registry;

use Amp\Cancellation;

interface MetricCollector {

    public function collectAndPush(iterable $streamIds, ?Cancellation $cancellation = null): int;
}
