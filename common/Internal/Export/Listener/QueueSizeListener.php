<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Listener;

use Nevay\OTelSDK\Common\Internal\Export\ExportListener;

/**
 * @internal
 */
final class QueueSizeListener implements ExportListener {

    public int $queueSize = 0;

    public function onExport(?int $count): void {
        // no-op
    }

    public function onFinished(?int $count): void {
        $this->queueSize -= $count;
    }
}
