<?php

declare(strict_types=1);

namespace Ahinest\LaravelPrintKit\Data;

/** Lista inmutable que conserva el orden y las repeticiones solicitadas. */
final class ResolvedLabelCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    private readonly array $labels;

    public function __construct(ResolvedLabel ...$labels)
    {
        $this->labels = array_values($labels);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->labels;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function toArray(): array
    {
        return array_map(fn (ResolvedLabel $label) => $label->toArray(), $this->labels);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
