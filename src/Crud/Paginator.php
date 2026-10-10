<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Crud;

final class Paginator
{
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly ?string $sort = null,
        public readonly string $direction = 'asc',
    ) {
    }

    public function getPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->getPages();
    }

    public function getFirst(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function getLast(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function getRange(int $around = 2): array
    {
        $pages = $this->getPages();
        $range = [];

        foreach (range(1, $pages) as $page) {
            if ($page === 1 || $page === $pages || abs($page - $this->page) <= $around) {
                $range[] = $page;
            } elseif (end($range) !== null) {
                $range[] = null;
            }
        }

        return $range;
    }
}