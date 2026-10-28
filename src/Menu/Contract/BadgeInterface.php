<?php

namespace NeoPHP\NeoAdmin\Menu\Contract;

interface BadgeInterface
{
    /**
     * Returns the value displayed in the badge of a menu item, computed on each page of the administration.
     *
     * @return int|string|null The value: a number (e.g. the records waiting for a moderation) or a short text; null, 0, or '' hides the badge
     */
    public function getValue(): int|string|null;
}