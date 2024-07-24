<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use function preg_match;

/**
 * Matches wildcard patterns and returns associated values.
 *
 * @template T
 *
 * @internal
 */
final class WildcardPatternMatcher {

    /**
     * @param list<T> $matchAll
     * @param array<string, list<T>> $static
     * @param list<string> $patterns
     * @param list<array<int, list<T>>> $marks
     *
     * @internal
     */
    public function __construct(
        private readonly array $matchAll,
        private readonly array $static,
        private readonly array $patterns,
        private readonly array $marks,
    ) {}

    /**
     * @return iterable<T>
     */
    public function match(string $value): iterable {
        yield from $this->matchAll;
        yield from $this->static[$value] ?? [];

        foreach ($this->patterns as $i => $pattern) {
            while (preg_match($pattern, $value, $matches)) {
                $mark = (int) $matches['MARK'];
                yield from $this->marks[$i][$mark];

                // Fail match in subsequent iterations
                $pattern[$mark + 2] = 'F';
                $pattern[$mark + 3] = ':';
            }
        }
    }
}
