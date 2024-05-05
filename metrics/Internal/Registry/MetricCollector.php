<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Registry;

use Amp\Cancellation;

/**
 * @internal
 */
interface MetricCollector {

    public function collectAndPush(iterable $streamIds, ?Cancellation $cancellation = null): int;
}
