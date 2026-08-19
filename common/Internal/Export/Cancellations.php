<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\NullCancellation;
use Amp\TimeoutCancellation;

/**
 * @internal
 */
final class Cancellations {

    public static function withTimeout(float $timeout, Cancellation $cancellation = new NullCancellation()): Cancellation {
        return $timeout
            ? new CompositeCancellation(new TimeoutCancellation($timeout), $cancellation)
            : $cancellation;
    }
}
