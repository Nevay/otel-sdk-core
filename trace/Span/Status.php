<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Trace\Span;

use OpenTelemetry\API\Trace\StatusCode;

enum Status {

    case Unset;
    case Ok;
    case Error;

    public static function fromApi(string $status): self {
        return match ($status) {
            StatusCode::STATUS_UNSET => self::Unset,
            StatusCode::STATUS_OK    => self::Ok,
            StatusCode::STATUS_ERROR => self::Error,
        };
    }

    public function compareTo(Status $other): int {
        return $this->ordinal() <=> $other->ordinal();
    }

    public function allowsDescription(): bool {
        return $this === self::Error;
    }

    public function ordinal(): int {
        return match ($this) {
            self::Unset => 0,
            self::Error => 1,
            self::Ok    => 2,
        };
    }
}
