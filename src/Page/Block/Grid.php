<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Grid extends Container
{
    protected int $columns;

    public function __construct(int $columns = 2)
    {
        $this->columns = max(1, min(4, $columns));
    }

    protected function getTemplate(): string
    {
        return '@admin/components/grid.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return ['columns' => $this->columns, 'blocks' => $this->buildBlocks($context)];
    }
}