<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;
use Nevay\OTelSDK\Metrics\ExemplarReservoir;
use Nevay\OTelSDK\Metrics\Exemplars;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlignedHistogramBucketExemplarReservoirEntry;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\AlignedHistogramBucketExemplars;
use Nevay\OTelSDK\Metrics\Internal\Exemplar\ExemplarAttributes;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ContextInterface;
use Random\Engine\PcgOneseq128XslRr64;
use Random\Randomizer;
use function array_fill;
use function ceil;
use function count;
use function lcg_value;
use function log;
use const PHP_VERSION_ID;

/**
 * @see https://opentelemetry.io/docs/specs/otel/metrics/sdk/#alignedhistogrambucketexemplarreservoir
 */
final class AlignedHistogramBucketExemplarReservoir implements ExemplarReservoir {

    private readonly Randomizer $randomizer;

    private readonly array $boundaries;
    /** @var list<AlignedHistogramBucketExemplarReservoirEntry|null> */
    private array $buckets;

    /**
     * @param list<float|int> $boundaries
     */
    public function __construct(array $boundaries, Randomizer $randomizer = new Randomizer(new PcgOneseq128XslRr64())) {
        $this->randomizer = $randomizer;
        $this->boundaries = $boundaries;
        $this->buckets = array_fill(0, count($boundaries) + 1, null);
    }

    public function offer(float|int $value, Attributes $attributes, ContextInterface $context, int $timestamp): void {
        for ($i = 0, $n = count($this->boundaries); $i < $n && $this->boundaries[$i] < $value; $i++) {}

        $entry = $this->buckets[$i] ??= new AlignedHistogramBucketExemplarReservoirEntry();

        if (--$entry->jumpWeight > 0) {
            return;
        }

        $entry->value = $value;
        $entry->timestamp = $timestamp;
        $entry->attributes = $attributes;

        $spanContext = Span::fromContext($context)->getContext();
        $entry->spanContext = $spanContext->isValid()
            ? $spanContext
            : null;

        $entry->priority = $this->rand($entry->priority);
        $entry->jumpWeight = (int) (string) ceil(log($this->rand()) / log($entry->priority));
    }

    public function collect(Attributes $dataPointAttributes): Exemplars {
        $exemplars = [];
        $priorities = [];
        foreach ($this->buckets as $bucket => $entry) {
            if (!$entry?->priority) {
                continue;
            }

            $exemplars[$bucket] = new Exemplar(
                $entry->value,
                $entry->timestamp,
                ExemplarAttributes::filter($dataPointAttributes, $entry->attributes),
                $entry->spanContext,
            );
            $priorities[$bucket] = $entry->priority;

            unset(
                $entry->value,
                $entry->timestamp,
                $entry->attributes,
                $entry->spanContext,
            );
            $entry->priority = 0;
            $entry->jumpWeight = 0;
        }

        return new AlignedHistogramBucketExemplars($exemplars, $priorities);
    }

    private function rand(float $min = 0): float {
        return PHP_VERSION_ID >= 80300
            ? $this->randomizer->getFloat($min, 1, \Random\IntervalBoundary::OpenOpen)
            : lcg_value() * (1 - $min) + $min;
    }
}
