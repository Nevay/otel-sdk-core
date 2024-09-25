<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Internal;

use function array_key_first;
use function array_pop;
use function array_splice;
use function assert;
use function count;
use function preg_match;
use function preg_quote;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function strcspn;
use function strlen;
use function strspn;
use function substr;
use function substr_compare;

/**
 * Matches wildcard patterns and returns associated values.
 *
 * Wildcard patterns may use the following special characters:
 * - `?` matches any single character
 * - `*` matches any number of any characters including none
 *
 * @template T
 *
 * @internal
 */
final class WildcardPatternMatcherBuilder {

    /** @var list<T> */
    private array $matchAll = [];
    /** @var array<string, list<T>> */
    private array $static = [];
    /** @var WildcardPatternMatcherTrie<T> */
    private WildcardPatternMatcherTrie $root;

    /*
     * Cache for compiled regex patterns.
     * Invalidated on ::add() w/ wildcard pattern.
     */
    /** @var list<string>|null */
    private ?array $patterns = [];
    /** @var list<array<int, list<T>>>|null */
    private ?array $marks = [];

    public function __construct() {
        $this->root = new WildcardPatternMatcherTrie('');
    }

    /**
     * @param string $pattern wildcard pattern to add
     * @param T $value value to associate wih the pattern
     */
    public function add(string $pattern, mixed $value): void {
        if (strcspn($pattern, "*?") === strlen($pattern)) {
            $this->static[$pattern][] = $value;
            return;
        }
        if ($pattern === '*') {
            $this->matchAll[] = $value;
            return;
        }

        $this->patterns = null;
        $this->marks = null;

        $p = $this->root;
        for ($i = 0, $length = strlen($pattern); $i < $length;) {
            $c = $pattern[$i];
            if (!($n = $p->children[$c] ?? null)) {
                $p = $p->children[$c] = new WildcardPatternMatcherTrie(substr($pattern, $i));
                $i = $length;
            } elseif (!substr_compare($pattern, $n->substr, $i, $l = strlen($n->substr))) {
                $p = $n;
                $i += $l;
            } else {
                $r = strspn(substr($n->substr, 0, $length - $i) ^ substr($pattern, $i, $l), "\0");
                $p = $p->children[$c] = new WildcardPatternMatcherTrie(substr($n->substr, 0, $r), [$n->substr[$r] => $n]);
                $n->substr = substr($n->substr, $r);
                $i += $r;
            }
        }

        $p->values[] = $value;
    }

    /**
     * @return WildcardPatternMatcher<T>
     */
    public function build(): WildcardPatternMatcher {
        if ($this->patterns === null) {
            $this->compile();
        }

        assert($this->patterns !== null);
        assert($this->marks !== null);

        return new WildcardPatternMatcher($this->matchAll, $this->static, $this->patterns, $this->marks);
    }

    private function compile(): void {
        $nodes = [];
        if ($this->root->children) {
            $nodes[] = $this->root;
        }

        $patterns = [];
        $marks = [];
        while ($node = array_pop($nodes)) {
            $m = [];
            $p = '/^';
            $this->_compile($node, $p, $m);
            $p .= '$/';

            set_error_handler(static fn() => null);
            try {
                if (preg_match($p, '') === false) {
                    self::split($nodes[] = clone $node, $nodes[] = clone $node);
                    continue;
                }
            } finally {
                restore_error_handler();
            }

            $patterns[] = $p;
            $marks[] = $m;
        }

        $this->patterns = $patterns;
        $this->marks = $marks;
    }

    private static function split(WildcardPatternMatcherTrie $left, WildcardPatternMatcherTrie $right): void {
        $right->values = [];

        if (($c = count($left->children)) === 1) {
            $k = array_key_first($left->children);
            self::split(
                $left->children[$k] = clone $left->children[$k],
                $right->children[$k] = clone $right->children[$k],
            );
        } else {
            assert($c >= 2);
            array_splice($left->children, $c >> 1);
            array_splice($right->children, 0, $c >> 1);
        }
    }

    private static function _compile(WildcardPatternMatcherTrie $node, string &$pattern, array &$marks): void {
        $pattern .= strtr(preg_quote($node->substr, '/'), ['\\?' => '.', '\\*' => '.*']);

        if ($node->children) {
            $pattern .= '(?';
        }
        foreach ($node->children as $child) {
            $pattern .= '|';
            self::_compile($child, $pattern, $marks);
        }
        if ($node->children && $node->values) {
            $pattern .= '|';
        }
        if ($node->values) {
            $mark = strlen($pattern);
            $pattern .= sprintf('(*:%d)', $mark);
            $marks[$mark] = $node->values;
        }
        if ($node->children) {
            $pattern .= ')';
        }
    }
}
