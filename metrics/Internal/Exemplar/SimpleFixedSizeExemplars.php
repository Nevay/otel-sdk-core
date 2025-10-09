<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;
use Nevay\OTelSDK\Metrics\Exemplars;
use function array_merge;
use function array_multisort;
use function array_slice;
use const SORT_DESC;
use const SORT_NUMERIC;

/**
 * @internal
 */
final class SimpleFixedSizeExemplars implements Exemplars {

    /**
     * @param int $size
     * @param array<int, Exemplar> $exemplars
     * @param array<int, float> $priorities
     */
    public function __construct(
        private readonly int $size,
        private readonly array $exemplars,
        private readonly array $priorities,
    ) {}

    public function merge(Exemplars $into): Exemplars {
        if (!$into instanceof $this) {
            return $this;
        }

        $priorities = array_merge($into->priorities, $this->priorities);
        $exemplars = array_merge($into->exemplars, $this->exemplars);

        array_multisort($priorities, SORT_NUMERIC, SORT_DESC, $exemplars);

        return new self(
            $this->size,
            array_slice($exemplars, 0, $this->size),
            array_slice($priorities, 0, $this->size),
        );
    }

    public function enrich(Attributes $attributes): Exemplars {
        return new self(
            $this->size,
            ExemplarAttributes::enrich($attributes, $this->exemplars),
            $this->priorities,
        );
    }

    public function extract(): array {
        return $this->exemplars;
    }
}
