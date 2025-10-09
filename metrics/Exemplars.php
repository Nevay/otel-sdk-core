<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;

interface Exemplars {

    public function merge(Exemplars $into): Exemplars;

    public function enrich(Attributes $attributes): Exemplars;

    /**
     * @return array<Exemplar>
     */
    public function extract(): array;
}
