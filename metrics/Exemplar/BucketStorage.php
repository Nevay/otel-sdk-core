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
        for ($i = 0; $i < $size; $i++) {
            $this->buckets[$i] = new BucketEntry();
        }
    }

    public function store(int $bucket, int|string $index, float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        assert($bucket <= count($this->buckets));

        $exemplar = $this->buckets[$bucket] ??= new BucketEntry();
        $exemplar->index = $index;
        $exemplar->value = $value;
        $exemplar->timestamp = $timestamp;
        $exemplar->attributes = $attributes;

        $spanContext = Span::fromContext($context)->getContext();
        $exemplar->spanContext = $spanContext->isValid()
            ? $spanContext
            : null;
    }

    /**
     * @param array<Attributes> $dataPointAttributes
     * @return array<Exemplar>
     */
    public function collect(array $dataPointAttributes): array {
        $exemplars = [];
        foreach ($this->buckets as $bucket => $entry) {
            if (!isset($entry->index)) {
                continue;
            }

            $exemplars[$bucket] = new Exemplar(
                $entry->index,
                $entry->value,
                $entry->timestamp,
                $this->filterExemplarAttributes(
                    $dataPointAttributes[$entry->index],
                    $entry->attributes,
                ),
                $entry->spanContext,
            );
            unset(
                $entry->index,
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
