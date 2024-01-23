<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextStorageInterface;

final class ContextResolver {

    public static function resolve(ContextInterface|false|null $context, ?ContextStorageInterface $contextStorage): ContextInterface {
        /** @psalm-suppress InternalMethod */
        return Context::resolve($context, $contextStorage);
    }

    public static function emptyContext(): ContextInterface {
        static $empty;
        return $empty ??= self::resolve(false, null);
    }
}
