<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export\Listener;

use Nevay\OTelSDK\Common\Internal\Export\ExportListener;

final class NoopListener implements ExportListener {

    public function onExport(int $count): void {
        // no-op
    }

    public function onFinished(int $count): void {
        // no-op
    }
}
