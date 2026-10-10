<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\Contract\BlockInterface;
use NeoPHP\NeoAdmin\Page\PageContext;

abstract class AbstractBlock implements BlockInterface
{
    protected int $span = 1;

    public function span(int $span): static
    {
        $this->span = max(1, min(4, $span));

        return $this;
    }

    public function getSpan(): int
    {
        return $this->span;
    }

    public function build(PageContext $context): array
    {
        return ['template' => $this->getTemplate(), 'context' => $this->getContext($context), 'span' => $this->span];
    }

    abstract protected function getTemplate(): string;

    abstract protected function getContext(PageContext $context): array;
}