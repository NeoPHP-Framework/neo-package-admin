<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\PageContext;

class Stat extends AbstractBlock
{
    public function __construct(
        protected string $label,
        protected mixed $value,
        protected ?string $icon = null,
        protected ?float $trend = null,
        protected ?string $help = null,
        protected ?string $url = null,
        protected string $more = 'View all',
    ) {
    }

    public function more(string $label): static
    {
        $this->more = $label;

        return $this;
    }

    protected function getTemplate(): string
    {
        return '@admin/components/stat.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $trend = null;

        if ($this->trend !== null) {
            $rounded = round($this->trend, 1);
            $trend = [
                'value' => ($rounded > 0 ? '+' : '') . $context->formatNumber($rounded, $rounded == (int) $rounded ? 0 : 1) . ' %',
                'direction' => $rounded > 0 ? 'up' : ($rounded < 0 ? 'down' : 'flat'),
            ];
        }

        return [
            'label' => $this->label,
            'value' => is_int($this->value) || is_float($this->value) ? $context->formatNumber($this->value, is_float($this->value) && floor($this->value) != $this->value ? 1 : 0) : (string) $this->value,
            'icon' => $this->icon,
            'trend' => $trend,
            'help' => $this->help,
            'url' => $this->url,
            'more' => $this->more,
        ];
    }
}