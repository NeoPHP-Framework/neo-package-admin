<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Template extends AbstractBlock
{
    public function __construct(protected string $name, protected array $data = [])
    {
    }

    protected function getTemplate(): string
    {
        return $this->name;
    }

    protected function getContext(PageContext $context): array
    {
        return $this->data;
    }
}