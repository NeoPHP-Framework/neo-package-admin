<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Tile extends AbstractBlock
{
    public function __construct(protected string $label, protected string $url, protected ?string $icon = null)
    {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/tile.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return ['label' => $this->label, 'url' => $this->url, 'icon' => $this->icon ?? 'list'];
    }
}