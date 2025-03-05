<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Provider;

use Amp\Cancellation;
use Amp\Future;
use Nevay\OTelSDK\Common\Provider;
use function Amp\async;

final class MultiProvider implements Provider {

    /**
     * @param iterable<Provider> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {}

    public function shutdown(?Cancellation $cancellation = null): bool {
        $futures = [];
        $shutdown = static function(Provider $p, ?Cancellation $cancellation): bool {
            return $p->shutdown($cancellation);
        };
        foreach ($this->providers as $provider) {
            $futures[] = async($shutdown, $provider, $cancellation);
        }

        [$errors, $results] = Future\awaitAll($futures);

        foreach ($errors as $error) {
            throw $error;
        }
        foreach ($results as $success) {
            if (!$success) {
                return false;
            }
        }

        return true;
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        $futures = [];
        $forceFlush = static function(Provider $p, ?Cancellation $cancellation): bool {
            return $p->forceFlush($cancellation);
        };
        foreach ($this->providers as $provider) {
            $futures[] = async($forceFlush, $provider, $cancellation);
        }

        [$errors, $results] = Future\awaitAll($futures);

        foreach ($errors as $error) {
            throw $error;
        }
        foreach ($results as $success) {
            if (!$success) {
                return false;
            }
        }

        return true;
    }
}
