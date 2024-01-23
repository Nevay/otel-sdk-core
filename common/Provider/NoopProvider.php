<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Provider;

use Amp\Cancellation;
use Nevay\OTelSDK\Common\Provider;

final class NoopProvider implements Provider {

    public function shutdown(?Cancellation $cancellation = null): bool {
        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return true;
    }
}
