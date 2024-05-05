<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Driver;

use AssertionError;
use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessorDriver;

/**
 * @template TData
 * @implements ExportingProcessorDriver<TData, TData>
 *
 * @internal
 */
final class SimpleDriver implements ExportingProcessorDriver {

    public function getPending(): mixed {
        throw new AssertionError();
    }

    public function hasPending(): bool {
        return false;
    }

    public function isBuffered(): bool {
        return false;
    }

    public function count(mixed $data): int {
        return 1;
    }

    public function finalize(mixed $data): iterable {
        return [$data];
    }
}
