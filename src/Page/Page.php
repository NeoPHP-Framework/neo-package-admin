<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page;

use Closure;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\NeoAdmin\Page\Block\Container;

class Page extends Container
{
    protected ?string $heading = null;

    protected ?string $description = null;

    protected array $actions = [];

    public function __construct(protected string $title, protected ?Closure $renderer = null)
    {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function heading(?string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function action(Action $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    public function render(int $status = 200): Response
    {
        if ($this->renderer === null) {
            throw new NeoAdminException('The page "{title}" cannot be rendered: create it with $this->page() in a controller extending AbstractAdminController.', 0, null, ['title' => $this->title]);
        }

        return ($this->renderer)($this, $status);
    }

    protected function getTemplate(): string
    {
        return '@admin/page.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        return [
            'title' => $this->title,
            'heading' => $this->heading,
            'description' => $this->description,
            'actions' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            'blocks' => $this->buildBlocks($context),
        ];
    }
}