<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs\Internal;

use Nevay\OtelSDK\Common\AttributesFactory;
use Nevay\OtelSDK\Common\Clock;
use Nevay\OtelSDK\Common\Resource;
use Nevay\OtelSDK\Logs\LogRecordProcessor;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class LoggerState {

    public function __construct(
        public readonly ?ContextStorageInterface $contextStorage,
        public readonly Resource $resource,
        public readonly Clock $clock,
        public readonly LogRecordProcessor $logRecordProcessor,
        public readonly AttributesFactory $logRecordAttributesFactory,
        public readonly ?LoggerInterface $logger,
    ) {}
}
