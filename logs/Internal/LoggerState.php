<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs\Internal;

use Nevay\OTelSDK\Common\AttributesFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Logs\LogRecordProcessor;
use OpenTelemetry\Context\ContextStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class LoggerState {

    public LogRecordProcessor $logRecordProcessor;

    public function __construct(
        public readonly ?ContextStorageInterface $contextStorage,
        public readonly Resource $resource,
        public readonly Clock $clock,
        public readonly AttributesFactory $logRecordAttributesFactory,
        public readonly ?LoggerInterface $logger,
    ) {}
}
