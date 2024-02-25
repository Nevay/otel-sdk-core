<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Driver;

use Nevay\OTelSDK\Common\Internal\Export\ExportingProcessorDriver;
use function count;

/**
 * @template TData
 * @implements ExportingProcessorDriver<list<TData>, TData>
 */
final class BatchDriver implements ExportingProcessorDriver {

    /** @var list<TData> */
    public array $batch = [];

    public function getPending(): array {
        try {
            return $this->batch;
        } finally {
            $this->batch = [];
        }
    }

    public function hasPending(): bool {
        return $this->batch !== [];
    }

    public function isBuffered(): bool {
        return true;
    }

    public function count(mixed $data): int {
        return count($data);
    }

    public function finalize(mixed $data): iterable {
        return $data;
    }
}
