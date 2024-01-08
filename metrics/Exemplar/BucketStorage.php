<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Metrics\Exemplar;

use Nevay\OtelSDK\Common\Attributes;
use Nevay\OtelSDK\Metrics\Data\Exemplar;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;
use function array_fill;
use function assert;
use function count;

/**
 * @internal
 */
final class BucketStorage {

    /** @var array<int, BucketEntry> */
    private array $buckets;

    public function __construct(int $size = 0) {
        $this->buckets = array_fill(0, $size, null);
    }

    public function store(int $bucket, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        assert($bucket <= count($this->buckets));

        $exemplar = $this->buckets[$bucket] ??= new BucketEntry();
        $exemplar->value = $value;
        $exemplar->timestamp = $timestamp;
        $exemplar->attributes = $attributes;

        $spanContext = Span::fromContext($context)->getContext();
        $exemplar->spanContext = $spanContext->isValid()
            ? $spanContext
            : null;
    }

    /**
     * @return array<Exemplar>
     */
    public function collect(Attributes $dataPointAttributes): array {
        $exemplars = [];
        foreach ($this->buckets as $bucket => $entry) {
            if (!isset($entry->value)) {
                continue;
            }

            $exemplars[$bucket] = new Exemplar(
                $entry->value,
                $entry->timestamp,
                $this->filterExemplarAttributes(
                    $dataPointAttributes,
                    $entry->attributes,
                ),
                $entry->spanContext,
            );
            unset(
                $entry->value,
                $entry->timestamp,
                $entry->attributes,
                $entry->spanContext
            );
        }

        return $exemplars;
    }

    private function filterExemplarAttributes(Attributes $dataPointAttributes, Attributes $exemplarAttributes): Attributes {
        $attributes = [];
        foreach ($exemplarAttributes as $key => $value) {
            if (!$dataPointAttributes->has($key)) {
                $attributes[$key] = $value;
            }
        }

        return new Attributes($attributes, $exemplarAttributes->getDroppedAttributesCount());
    }
}
