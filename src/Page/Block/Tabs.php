<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Tabs extends AbstractBlock
{
    public function __construct(protected string $parameter, protected array $options, protected int|string|null $current = null)
    {
    }

    protected function getTemplate(): string
    {
        return '@admin/components/tabs.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $tabs = [];

        foreach ($this->options as $value => $label) {
            $tabs[] = [
                'label' => (string) $label,
                'url' => $context->url([$this->parameter => $value, 'page' => null]),
                'active' => (string) $value === (string) $this->current,
            ];
        }

        return ['tabs' => $tabs];
    }
}