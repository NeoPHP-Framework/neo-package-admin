<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Crud\Paginator;
use NeoPHP\NeoAdmin\Page\Action;
use NeoPHP\NeoAdmin\Page\Column;
use NeoPHP\NeoAdmin\Page\PageContext;

class Table extends AbstractBlock
{
    protected array $rows;

    protected array $columns = [];

    protected ?array $pagination = null;

    protected array $actions = [];

    public function __construct(
        protected ?string $title,
        iterable $rows,
        array $columns,
        protected ?string $icon = null,
        protected ?string $empty = null,
        protected mixed $rowActions = null,
    ) {
        $this->rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);

        foreach ($columns as $key => $column) {
            $this->columns[] = $column instanceof Column ? $column : new Column((string) $key, (string) $column);
        }
    }

    public function rowActions(?callable $actions): static
    {
        $this->rowActions = $actions;

        return $this;
    }

    public function action(Action $action): static
    {
        $this->actions[] = $action;

        return $this;
    }

    public function paginate(int $total, int $page, int $perPage, string $parameter = 'page'): static
    {
        $this->pagination = ['total' => max(0, $total), 'page' => max(1, $page), 'per_page' => max(1, $perPage), 'parameter' => $parameter];

        return $this;
    }

    protected function getTemplate(): string
    {
        return '@admin/components/table.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $columns = [];

        foreach ($this->columns as $column) {
            if ($column->getType() === null) {
                $type = 'text';

                foreach ($this->rows as $row) {
                    $value = $column->value($row);

                    if ($value !== null && $value !== '') {
                        $type = $column->guess($value);
                        break;
                    }
                }

                $column = $column->withType($type);
            }

            $columns[] = $column;
        }

        $rows = [];

        foreach ($this->rows as $row) {
            $actions = [];

            if (is_callable($this->rowActions)) {
                foreach ((array) ($this->rowActions)($row) as $action) {
                    if ($action instanceof Action) {
                        $actions[] = $action->toArray();
                    }
                }
            }

            $rows[] = [
                'cells' => array_map(static fn (Column $column): array => $column->cell($row, $context), $columns),
                'actions' => $actions,
            ];
        }

        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'columns' => array_map(static fn (Column $column): array => ['label' => $column->getLabel(), 'numeric' => $column->isNumeric()], $columns),
            'rows' => $rows,
            'has_actions' => is_callable($this->rowActions),
            'actions' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            'empty' => $this->empty ?? 'Nothing here yet.',
            'pagination' => $this->buildPagination($context),
            'framed' => $context->isFramed(),
        ];
    }

    protected function buildPagination(PageContext $context): ?array
    {
        if ($this->pagination === null) {
            return null;
        }

        $paginator = new Paginator([], $this->pagination['page'], $this->pagination['per_page'], $this->pagination['total']);
        $parameter = $this->pagination['parameter'];
        $url = static fn (int $page): string => $context->url([$parameter => $page > 1 ? $page : null]);
        $pages = [];

        foreach ($paginator->getRange() as $page) {
            $pages[] = $page === null ? ['gap' => true] : ['gap' => false, 'label' => (string) $page, 'url' => $url($page), 'active' => $page === $paginator->page];
        }

        return [
            'first' => $paginator->getFirst(),
            'last' => $paginator->getLast(),
            'total' => $paginator->total,
            'pages' => $paginator->getPages() > 1 ? $pages : [],
            'previous' => $paginator->hasPrevious() ? $url($paginator->page - 1) : null,
            'next' => $paginator->hasNext() ? $url($paginator->page + 1) : null,
        ];
    }
}