<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use function strlen;

/**
 * @template T
 *
 * @internal
 */
final class WildcardPatternMatcherTrie {

    /**
     * @param string $substr
     * @param array<WildcardPatternMatcherTrie<T>> $children
     * @param list<T> $values
     */
    public function __construct(
        public string $substr,
        public array $children = [],
        public array $values = [],
    ) {}

    public function size(): int {
        $size = strlen($this->substr);
        foreach ($this->children as $child) {
            $size += $child->size();
        }

        return $size;
    }
}
