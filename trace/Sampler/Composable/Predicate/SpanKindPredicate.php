<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;

use Nevay\OTelSDK\Trace\Sampler\Composable\Predicate;
use Nevay\OTelSDK\Trace\SamplingParams;
use Nevay\OTelSDK\Trace\Span;
use function array_column;
use function implode;
use function in_array;
use function sprintf;

/**
 * @experimental
 */
final class SpanKindPredicate implements Predicate {

    private readonly array $spanKinds;

    public function __construct(Span\Kind $spanKind, Span\Kind ...$spanKinds) {
        $this->spanKinds = [$spanKind, ...$spanKinds];
    }

    public function matches(SamplingParams $params): bool {
        return in_array($params->spanKind, $this->spanKinds, true);
    }

    public function __toString(): string {
        return sprintf('Span.Kind==[%s]', implode(',', array_column($this->spanKinds, 'name')));
    }
}
