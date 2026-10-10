<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class LinkList extends AbstractBlock
{
    public function __construct(
        protected string $title,
        protected array $links,
        protected ?string $icon = null,
        protected ?string $empty = null,
        protected ?string $more = null,
        protected string $moreLabel = 'View all',
    ) {
    }

    public function more(?string $url, string $label = 'View all'): static
    {
        $this->more = $url;
        $this->moreLabel = $label;

        return $this;
    }

    protected function getTemplate(): string
    {
        return '@admin/components/links.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $links = [];

        foreach ($this->links as $link) {
            $link = is_array($link) ? $link : ['label' => (string) $link];
            $links[] = [
                'label' => (string) ($link['label'] ?? ''),
                'url' => isset($link['url']) ? (string) $link['url'] : null,
                'icon' => isset($link['icon']) ? (string) $link['icon'] : null,
                'meta' => isset($link['meta']) ? (is_int($link['meta']) || is_float($link['meta']) ? $context->formatNumber($link['meta']) : (string) $link['meta']) : null,
                'target' => isset($link['target']) ? (string) $link['target'] : null,
            ];
        }

        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'links' => $links,
            'empty' => $this->empty ?? 'Nothing here yet.',
            'more' => $this->more,
            'more_label' => $this->moreLabel,
            'framed' => $context->isFramed(),
        ];
    }
}