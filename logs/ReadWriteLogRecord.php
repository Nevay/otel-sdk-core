<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Logs;

use OpenTelemetry\API\Trace\SpanContextInterface;

interface ReadWriteLogRecord extends ReadableLogRecord {

    public function setTimestamp(?int $timestamp): self;

    public function setObservedTimestamp(?int $observedTimestamp): self;

    public function setSpanContext(?SpanContextInterface $spanContext): self;

    public function setSeverityText(?string $severityText): self;

    public function setSeverityNumber(?int $severityNumber): self;

    public function setBody(mixed $body): self;

    public function setAttribute(string $key, mixed $value): self;

    public function setAttributes(iterable $attributes): self;
}
