<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal\SpanSuppression;

use GMP;

/**
 * @internal
 */
final class SemanticConventionSuppressionEntry {

    /**
     * @param list<string> $semanticConventions
     * @param list<string> $attributes
     * @param list<int|GMP> $masks
     */
    public function __construct(
        public readonly int|GMP $mask,
        public readonly array $semanticConventions,
        public readonly array $attributes,
        public readonly array $masks,
    ) {}
}
