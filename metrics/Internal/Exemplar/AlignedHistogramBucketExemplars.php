<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;
use Nevay\OTelSDK\Metrics\Exemplars;

/**
 * @internal
 */
final class AlignedHistogramBucketExemplars implements Exemplars {

    /**
     * @param array<int, Exemplar> $exemplars
     * @param array<int, float> $priorities
     */
    public function __construct(
        private readonly array $exemplars,
        private readonly array $priorities,
    ) {}

    public function merge(Exemplars $into): Exemplars {
        if (!$into instanceof $this) {
            return $this;
        }

        $exemplars = $into->exemplars;
        $priorities = $into->priorities;

        foreach ($this->priorities as $bucket => $priority) {
            if ($priority > ($priorities[$bucket] ?? 0)) {
                $priorities[$bucket] = $priority;
                $exemplars[$bucket] = $this->exemplars[$bucket];
            }
        }

        return new self($exemplars, $priorities);
    }

    public function enrich(Attributes $attributes): Exemplars {
        return new self(
            ExemplarAttributes::enrich($attributes, $this->exemplars),
            $this->priorities,
        );
    }

    public function extract(): array {
        return $this->exemplars;
    }
}
