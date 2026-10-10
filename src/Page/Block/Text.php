<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Text extends AbstractBlock
{
    public function __construct(protected string $text)
    {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/text.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return ['text' => $this->text];
    }
}