<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Contract;

use NeoPHP\NeoAdmin\Page\PageContext;

interface BlockInterface
{
    /**
     * Returns the number of grid columns the block spans.
     *
     * @return int The span, from 1 to 4
     */
    public function getSpan(): int;

    /**
     * Builds the view of the block: its template and the variables given to it.
     *
     * @param PageContext $context The context of the rendered page
     * @return array{template: string, context: array<string, mixed>, span: int} The view of the block
     */
    public function build(PageContext $context): array;
}