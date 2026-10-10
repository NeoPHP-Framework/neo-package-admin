<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Contract;

interface WidgetInterface
{
    /**
     * Builds the block displayed by the widget on the dashboard.
     *
     * @return BlockInterface The block: a stat, a table, a chart, a list of links...
     */
    public function build(): BlockInterface;
}