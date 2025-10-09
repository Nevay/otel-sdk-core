<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Exemplars;

/**
 * @internal
 */
enum EmptyExemplars implements Exemplars {

    case Instance;

    public function merge(Exemplars $into): Exemplars {
        return $into;
    }

    public function enrich(Attributes $attributes): Exemplars {
        return $this;
    }

    public function extract(): array {
        return [];
    }
}
