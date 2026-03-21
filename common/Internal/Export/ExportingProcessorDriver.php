<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

use Amp\Cancellation;
use Amp\Future;

/**
 * @template TData
 * @template TExport
 *
 * @internal
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
     * @return int|null
     */
    public function count(mixed $data): ?int;

    /**
     * @param Exporter<TExport> $exporter
     * @param TData $data
     * @return Future<bool>
     */
    public function export(Exporter $exporter, mixed $data, ?Cancellation $cancellation = null): Future;
}
