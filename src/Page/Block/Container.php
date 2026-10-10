<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\Component\Form\Contract\FormInterface;
use NeoPHP\NeoAdmin\Page\Contract\BlockInterface;
use NeoPHP\NeoAdmin\Page\PageContext;

abstract class Container extends AbstractBlock
{
    protected array $blocks = [];

    public function add(BlockInterface $block): static
    {
        $this->blocks[] = $block;

        return $this;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function grid(int $columns, callable $build): static
    {
        $grid = new Grid($columns);
        $build($grid);

        return $this->add($grid);
    }

    public function card(string $title, callable $build, ?string $icon = null, int $span = 1): static
    {
        $card = (new Card($title, $icon))->span($span);
        $build($card);

        return $this->add($card);
    }

    public function stat(string $label, mixed $value, ?string $icon = null, ?float $trend = null, ?string $help = null, ?string $url = null, int $span = 1): static
    {
        return $this->add((new Stat($label, $value, $icon, $trend, $help, $url))->span($span));
    }

    public function table(?string $title, iterable $rows, array $columns, ?string $icon = null, ?string $empty = null, ?callable $actions = null, int $span = 1): static
    {
        return $this->add((new Table($title, $rows, $columns, $icon, $empty, $actions))->span($span));
    }

    public function chart(string $title, array $rows, array $series, ?string $icon = 'chart', ?string $empty = null, int $span = 1): static
    {
        return $this->add((new Chart($title, $rows, $series, $icon, $empty))->span($span));
    }

    public function links(string $title, array $links, ?string $icon = null, ?string $empty = null, ?string $more = null, int $span = 1): static
    {
        return $this->add((new LinkList($title, $links, $icon, $empty, $more))->span($span));
    }

    public function tile(string $label, string $url, ?string $icon = null, int $span = 1): static
    {
        return $this->add((new Tile($label, $url, $icon))->span($span));
    }

    public function tabs(string $parameter, array $options, int|string|null $current = null): static
    {
        return $this->add(new Tabs($parameter, $options, $current));
    }

    public function alert(string $message, string $type = 'info'): static
    {
        return $this->add(new Alert($message, $type));
    }

    public function text(string $text): static
    {
        return $this->add(new Text($text));
    }

    public function empty(string $title, ?string $text = null, ?string $icon = 'box'): static
    {
        return $this->add(new EmptyState($title, $text, $icon));
    }

    public function form(FormInterface $form, string $submit = 'Save', ?string $cancel = null, ?string $title = null): static
    {
        return $this->add(new Form($form, $submit, $cancel, $title));
    }

    public function template(string $name, array $data = [], int $span = 1): static
    {
        return $this->add((new Template($name, $data))->span($span));
    }

    protected function buildBlocks(PageContext $context): array
    {
        return array_map(static fn (BlockInterface $block): array => $block->build($context), $this->blocks);
    }
}