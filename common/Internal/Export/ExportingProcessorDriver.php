<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

/**
 * @template TData
 * @template TExport
 */
interface ExportingProcessorDriver {

    /**
     * @return TData
     */
    public function getPending(): mixed;

    public function hasPending(): bool;

    public function isBuffered(): bool;

    /**
     * @param TData $data
     * @return int
     */
    public function count(mixed $data): int;

    /**
     * @param TData $data
     * @return iterable<TExport>
     */
    public function finalize(mixed $data): iterable;
}
