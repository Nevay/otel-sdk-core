<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\SimpleFixedSizeExemplarReservoirEntry;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;
use function array_fill;
use function ceil;
use function lcg_value;
use function log;
use function min;
use const PHP_VERSION_ID;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#simplefixedsizeexemplarreservoir
 */
final class SimpleFixedSizeExemplarReservoir implements ExemplarReservoir {

    private readonly Randomizer $randomizer;

    /** @var list<SimpleFixedSizeExemplarReservoirEntry> */
    private array $buckets;
    private SimpleFixedSizeExemplarReservoirEntry $head;
    private int $jumpWeight = 0;

    /**
     * @param int<1, max> $size
     */
    public function __construct(int $size, Randomizer $randomizer = new Randomizer(new PcgOneseq128XslRr64())) {
        $this->randomizer = $randomizer;
        $this->buckets = array_fill(0, $size, null);
        for ($i = 0; $i < $size; $i++) {
            $this->buckets[$i] = new SimpleFixedSizeExemplarReservoirEntry();
        }
        $this->head = $this->buckets[0];
    }

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        if (--$this->jumpWeight > 0) {
            return;
        }

        $entry = $this->head;

        $entry->value = $value;
        $entry->timestamp = $timestamp;
        $entry->attributes = $attributes;

        $spanContext = Span::fromContext($context)->getContext();
        $entry->spanContext = $spanContext->isValid()
            ? $spanContext
            : null;

        $entry->priority = $this->rand($entry->priority);

        $this->head = min($this->buckets);
        $this->jumpWeight = (int) (string) ceil(log($this->rand()) / log($this->head->priority));
    }

    public function collect(Attributes $dataPointAttributes): array {
        $exemplars = [];
        foreach ($this->buckets as $bucket => $entry) {
            if (!$entry->priority) {
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
                $entry->spanContext,
            );
            $entry->priority = 0;
        }

        $this->jumpWeight = 0;

        return $exemplars;
    }

    private function filterExemplarAttributes(Attributes $dataPointAttributes, Attributes $exemplarAttributes): Attributes {
        $attributes = $exemplarAttributes->toArray();
        foreach ($dataPointAttributes->toArray() as $key => $_) {
            unset($attributes[$key]);
        }

        return new Attributes($attributes, $exemplarAttributes->getDroppedAttributesCount());
    }

    private function rand(float $min = 0): float {
        return PHP_VERSION_ID >= 80300
            ? $this->randomizer->getFloat($min, 1, \Random\IntervalBoundary::OpenOpen)
            : lcg_value() * (1 - $min) + $min;
    }
}
