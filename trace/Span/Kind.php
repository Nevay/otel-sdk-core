<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Span;

use OpenTelemetry\API\Trace\SpanKind;

enum Kind {

    case Internal;
    case Client;
    case Server;
    case Producer;
    case Consumer;

    public static function fromApi(int $kind): self {
        return match ($kind) {
            SpanKind::KIND_INTERNAL => self::Internal,
            SpanKind::KIND_CLIENT   => self::Client,
            SpanKind::KIND_SERVER   => self::Server,
            SpanKind::KIND_PRODUCER => self::Producer,
            SpanKind::KIND_CONSUMER => self::Consumer,
        };
    }
}
