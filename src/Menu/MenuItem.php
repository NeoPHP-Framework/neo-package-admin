<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Menu;

final class MenuItem
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $label,
        public readonly ?string $icon = null,
        public readonly ?string $url = null,
        public readonly bool $active = false,
        public readonly ?string $badge = null,
        public readonly ?string $target = null,
        public readonly array $children = [],
    ) {
    }

    public function isSection(): bool
    {
        return $this->type === 'section';
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function isOpen(): bool
    {
        if ($this->active) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isOpen()) {
                return true;
            }
        }

        return false;
    }
}