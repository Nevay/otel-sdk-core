<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal\Export;

interface ExportListener {

    public function onExport(int $count): void;

    public function onFinished(int $count): void;
}
