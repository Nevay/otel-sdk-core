<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Common\Resource;
use OpenTelemetry\API\Trace\SpanContextInterface;

interface ReadableLogRecord {

    public function getInstrumentationScope(): InstrumentationScope;

    public function getResource(): Resource;

    public function getTimestamp(): ?int;

    public function getObservedTimestamp(): ?int;

    public function getSpanContext(): ?SpanContextInterface;

    public function getSeverityText(): ?string;

    public function getSeverityNumber(): ?int;

    public function getBody(): mixed;

    public function getAttributes(): Attributes;

    public function getEventName(): ?string;
}
