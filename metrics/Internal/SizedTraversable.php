<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal;

use Countable;
use IteratorAggregate;
use Traversable;

final class SizedTraversable implements IteratorAggregate, Countable {

    public function __construct(
        private readonly Traversable $traversable,
        private readonly int $count,
    ) {}

    public function getIterator(): Traversable {
        return $this->traversable;
    }

    public function count(): int {
        return $this->count;
    }
}
