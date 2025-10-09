<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\Exemplar;

use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Metrics\Data\Exemplar;
use function array_map;

/**
 * @internal
 */
final class ExemplarAttributes {

    public static function filter(Attributes $dataPointAttributes, Attributes $exemplarAttributes): Attributes {
        $attributes = $exemplarAttributes->toArray();
        foreach ($dataPointAttributes->toArray() as $key => $_) {
            unset($attributes[$key]);
        }

        return new Attributes($attributes, $exemplarAttributes->getDroppedAttributesCount());
    }

    /**
     * @param array<Exemplar> $exemplars
     * @return array<Exemplar>
     */
    public static function enrich(Attributes $attributes, array $exemplars): array {
        return array_map(
            static fn(Exemplar $exemplar): Exemplar => new Exemplar(
                $exemplar->value,
                $exemplar->timestamp,
                new Attributes($exemplar->attributes->toArray() + $attributes->toArray(), $exemplar->attributes->getDroppedAttributesCount()),
                $exemplar->spanContext,
            ),
            $exemplars,
        );
    }
}
