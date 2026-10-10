<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\Action;
use NeoPHP\NeoAdmin\Page\PageContext;

class Card extends Container
{
    protected array $actions = [];

    public function __construct(protected string $title, protected ?string $icon = null)
    {
    }

    public function action(Action $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    protected function getTemplate(): string
    {
        return '@admin/components/card.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'actions' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            'blocks' => $this->buildBlocks($context->nested()),
        ];
    }
}