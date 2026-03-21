<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;

final class LoggerState {

    /**
     * @param Configurator<LoggerConfig> $configurator
     * @param array<LogRecordProcessor> $logRecordProcessors
     */
    public function __construct(
        public Configurator $configurator,
        public array $logRecordProcessors,
        public Resource $resource,
        public AttributesFactory $logRecordAttributesFactory,
    ) {}
}
