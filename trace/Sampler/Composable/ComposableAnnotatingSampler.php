<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable;

use function iterator_to_array;
use function json_encode;
use function sprintf;

/**
 * @experimental
 */
final class ComposableAnnotatingSampler implements ComposableSampler {

    public function __construct(
        private readonly ComposableSampler $sampler,
        private readonly iterable $attributes,
    ) {}

    public function getSamplingIntent(
        SamplingParams $params,
        ?int $parentThreshold,
        bool $parentThresholdReliable,
    ): SamplingIntent {
        $intent = $this->sampler->getSamplingIntent(
            $params,
            $parentThreshold,
            $parentThresholdReliable,
        );

        $attributes = $intent->attributes;
        $annotated = $this->attributes;

        return new SamplingIntent(
            threshold: $intent->threshold,
            thresholdReliable: $intent->thresholdReliable,
            attributes: (static function() use ($attributes, $annotated): iterable {
                yield from $attributes;
                yield from $annotated;
            })(),
        );
    }

    public function __toString(): string {
        return sprintf('Annotating{Sampler=%s,Attributes=%s}', $this->sampler, json_encode(iterator_to_array($this->attributes)));
    }
}
