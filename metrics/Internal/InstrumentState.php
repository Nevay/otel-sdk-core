<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

/**
 * @internal
 */
final class InstrumentState {

    public function __construct(
        public bool $dormant = true,
    ) {}
}
