<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class EmptyState extends AbstractBlock
{
    public function __construct(protected string $title, protected ?string $text = null, protected ?string $icon = 'box')
    {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/empty.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return ['title' => $this->title, 'text' => $this->text, 'icon' => $this->icon, 'framed' => $context->isFramed()];
    }
}